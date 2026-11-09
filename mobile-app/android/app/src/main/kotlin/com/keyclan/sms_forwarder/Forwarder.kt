package com.keyclan.sms_forwarder

import android.content.ContentValues
import android.content.Context
import android.database.sqlite.SQLiteDatabase
import android.database.sqlite.SQLiteOpenHelper
import android.net.Uri
import android.os.Build
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import androidx.work.BackoffPolicy
import androidx.work.Constraints
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import org.json.JSONArray
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.security.KeyPairGenerator
import java.security.KeyStore
import java.security.MessageDigest
import java.security.PrivateKey
import java.security.Signature
import java.security.spec.ECGenParameterSpec
import java.util.UUID
import java.util.concurrent.TimeUnit
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec

private const val SIGNING_KEY = "device_signing_key"
private const val QUEUE_KEY = "queue_encryption_key"
private const val KEYSTORE = "AndroidKeyStore"

fun sha256Hex(text: String): String =
    MessageDigest.getInstance("SHA-256").digest(text.toByteArray(Charsets.UTF_8))
        .joinToString("") { "%02x".format(it) }

object Crypto {
    private fun keyStore() = KeyStore.getInstance(KEYSTORE).apply { load(null) }

    private fun queueKey(): SecretKey {
        (keyStore().getKey(QUEUE_KEY, null) as SecretKey?)?.let { return it }
        val generator = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, KEYSTORE)
        generator.init(
            KeyGenParameterSpec.Builder(QUEUE_KEY, KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT)
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM)
                .setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                .build()
        )
        return generator.generateKey()
    }

    fun encrypt(text: String): String {
        val cipher = Cipher.getInstance("AES/GCM/NoPadding")
        cipher.init(Cipher.ENCRYPT_MODE, queueKey())
        return Base64.encodeToString(cipher.iv + cipher.doFinal(text.toByteArray(Charsets.UTF_8)), Base64.NO_WRAP)
    }

    fun decrypt(stored: String): String {
        val data = Base64.decode(stored, Base64.NO_WRAP)
        val cipher = Cipher.getInstance("AES/GCM/NoPadding")
        cipher.init(Cipher.DECRYPT_MODE, queueKey(), GCMParameterSpec(128, data, 0, 12))
        return String(cipher.doFinal(data, 12, data.size - 12), Charsets.UTF_8)
    }

    /** Creates a fresh P-256 key inside the Keystore and returns the public half as base64. */
    fun newSigningKey(): String {
        val store = keyStore()
        if (store.containsAlias(SIGNING_KEY)) store.deleteEntry(SIGNING_KEY)
        val generator = KeyPairGenerator.getInstance(KeyProperties.KEY_ALGORITHM_EC, KEYSTORE)
        generator.initialize(
            KeyGenParameterSpec.Builder(SIGNING_KEY, KeyProperties.PURPOSE_SIGN)
                .setAlgorithmParameterSpec(ECGenParameterSpec("secp256r1"))
                .setDigests(KeyProperties.DIGEST_SHA256)
                .build()
        )
        return Base64.encodeToString(generator.generateKeyPair().public.encoded, Base64.NO_WRAP)
    }

    fun sign(text: String): String {
        val signature = Signature.getInstance("SHA256withECDSA")
        signature.initSign(keyStore().getKey(SIGNING_KEY, null) as PrivateKey)
        signature.update(text.toByteArray(Charsets.UTF_8))
        return Base64.encodeToString(signature.sign(), Base64.NO_WRAP)
    }

    /** Same calculation as pp_app_device_match_code() on the website. */
    fun matchCode(publicKey: String): String =
        "%06d".format(sha256Hex(publicKey).substring(0, 8).toLong(16) % 1000000)
}

class Prefs(context: Context) {
    private val prefs = context.getSharedPreferences("forwarder", Context.MODE_PRIVATE)

    var site: String
        get() = prefs.getString("site", "") ?: ""
        set(value) = prefs.edit().putString("site", value).apply()
    var deviceStatus: String
        get() = prefs.getString("device_status", "unpaired") ?: "unpaired"
        set(value) = prefs.edit().putString("device_status", value).apply()
    var matchCode: String
        get() = prefs.getString("match_code", "") ?: ""
        set(value) = prefs.edit().putString("match_code", value).apply()
    var supported: String
        get() = prefs.getString("supported", "[]") ?: "[]"
        set(value) = prefs.edit().putString("supported", value).apply()
    var lastError: String
        get() = prefs.getString("last_error", "") ?: ""
        set(value) = prefs.edit().putString("last_error", value).apply()
    val deviceUid: String
        get() = prefs.getString("uid", null) ?: UUID.randomUUID().toString().also {
            prefs.edit().putString("uid", it).apply()
        }

    fun clear() = prefs.edit().clear().apply()
}

class Store private constructor(context: Context) : SQLiteOpenHelper(context, "forwarder.db", null, 1) {
    override fun onCreate(db: SQLiteDatabase) {
        db.execSQL(
            """CREATE TABLE queue (
                seq INTEGER PRIMARY KEY AUTOINCREMENT,
                msg_id TEXT NOT NULL UNIQUE,
                sender TEXT NOT NULL,
                body TEXT NOT NULL,
                sent_stamp INTEGER NOT NULL,
                received_stamp INTEGER NOT NULL,
                sim INTEGER NOT NULL,
                state TEXT NOT NULL,
                reason TEXT NOT NULL DEFAULT '',
                attempts INTEGER NOT NULL DEFAULT 0,
                updated INTEGER NOT NULL)"""
        )
        db.execSQL("CREATE TABLE senders (address TEXT PRIMARY KEY, enabled_at INTEGER NOT NULL)")
    }

    override fun onUpgrade(db: SQLiteDatabase, oldVersion: Int, newVersion: Int) {}

    companion object {
        @Volatile private var instance: Store? = null
        fun get(context: Context): Store = instance ?: synchronized(this) {
            instance ?: Store(context.applicationContext).also { instance = it }
        }
    }

    fun selectedSenders(): Map<String, Long> {
        val senders = mutableMapOf<String, Long>()
        readableDatabase.rawQuery("SELECT address, enabled_at FROM senders", null).use {
            while (it.moveToNext()) senders[it.getString(0)] = it.getLong(1)
        }
        return senders
    }

    fun setSender(address: String, enabled: Boolean) {
        if (enabled) {
            writableDatabase.insertWithOnConflict(
                "senders", null,
                ContentValues().apply { put("address", address); put("enabled_at", System.currentTimeMillis()) },
                SQLiteDatabase.CONFLICT_IGNORE
            )
        } else {
            writableDatabase.delete("senders", "address = ?", arrayOf(address))
        }
    }

    /** The UNIQUE message ID makes this safe to call repeatedly for the same SMS. */
    fun enqueue(id: String, sender: String, body: String, sent: Long, received: Long, sim: Int) {
        // An ignored insert still uses up a sequence number, which the website would report as a missing SMS.
        if (stateOf(id) != null) return
        writableDatabase.insertWithOnConflict(
            "queue", null,
            ContentValues().apply {
                put("msg_id", id)
                put("sender", sender)
                put("body", Crypto.encrypt(body))
                put("sent_stamp", sent)
                put("received_stamp", received)
                put("sim", sim)
                put("state", "waiting")
                put("updated", System.currentTimeMillis())
            },
            SQLiteDatabase.CONFLICT_IGNORE
        )
    }

    fun pending(limit: Int): JSONArray {
        val batch = JSONArray()
        readableDatabase.rawQuery(
            "SELECT seq, msg_id, sender, body, sent_stamp, received_stamp, sim FROM queue WHERE state IN ('waiting','failed') ORDER BY seq LIMIT ?",
            arrayOf(limit.toString())
        ).use {
            while (it.moveToNext()) {
                batch.put(JSONObject().apply {
                    put("seq", it.getLong(0))
                    put("id", it.getString(1))
                    put("from", it.getString(2))
                    put("text", Crypto.decrypt(it.getString(3)))
                    put("sentStamp", it.getLong(4).toString())
                    put("receivedStamp", it.getLong(5).toString())
                    put("sim", it.getInt(6))
                })
            }
        }
        return batch
    }

    fun mark(id: String, state: String, reason: String) {
        writableDatabase.update(
            "queue",
            ContentValues().apply {
                put("state", state)
                put("reason", reason)
                put("updated", System.currentTimeMillis())
            },
            "msg_id = ?", arrayOf(id)
        )
    }

    fun markAllFailed(reason: String) {
        writableDatabase.execSQL(
            "UPDATE queue SET state = 'failed', reason = ?, attempts = attempts + 1, updated = ? WHERE state IN ('waiting','failed')",
            arrayOf<Any>(reason, System.currentTimeMillis())
        )
    }

    fun stateOf(id: String): String? =
        readableDatabase.rawQuery("SELECT state FROM queue WHERE msg_id = ?", arrayOf(id)).use {
            if (it.moveToFirst()) it.getString(0) else null
        }

    fun counts(): Map<String, Int> {
        val counts = mutableMapOf<String, Int>()
        readableDatabase.rawQuery("SELECT state, COUNT(*) FROM queue GROUP BY state", null).use {
            while (it.moveToNext()) counts[it.getString(0)] = it.getInt(1)
        }
        return counts
    }

    fun history(state: String?): List<Map<String, Any>> {
        val rows = mutableListOf<Map<String, Any>>()
        val where = if (state == null) "" else "WHERE state = ?"
        val args = if (state == null) null else arrayOf(state)
        readableDatabase.rawQuery(
            "SELECT sender, body, received_stamp, state, reason, attempts FROM queue $where ORDER BY seq DESC LIMIT 200", args
        ).use {
            while (it.moveToNext()) {
                rows.add(
                    mapOf(
                        "sender" to it.getString(0),
                        "body" to Crypto.decrypt(it.getString(1)),
                        "time" to it.getLong(2),
                        "state" to it.getString(3),
                        "reason" to it.getString(4),
                        "attempts" to it.getInt(5),
                    )
                )
            }
        }
        return rows
    }

    fun clear() {
        writableDatabase.delete("queue", null, null)
        writableDatabase.delete("senders", null, null)
    }
}

object Inbox {
    private val uri: Uri = Uri.parse("content://sms/inbox")
    private const val RESCAN_WINDOW = 7L * 24 * 60 * 60 * 1000

    fun messageId(uid: String, sender: String, sentStamp: Long, body: String) =
        sha256Hex("$uid\n${sender.trim()}\n$sentStamp\n$body")

    fun senders(context: Context): List<Map<String, Any>> {
        val found = linkedMapOf<String, Pair<Int, Long>>()
        context.contentResolver.query(uri, arrayOf("address", "date"), null, null, "date DESC")?.use {
            while (it.moveToNext()) {
                val address = it.getString(0) ?: continue
                val previous = found[address]
                found[address] = Pair((previous?.first ?: 0) + 1, previous?.second ?: it.getLong(1))
            }
        }
        val selected = Store.get(context).selectedSenders()
        val supported = JSONArray(Prefs(context).supported).let { list ->
            (0 until list.length()).map { list.getString(it).lowercase() }.toSet()
        }
        return found.map { (address, info) ->
            mapOf(
                "address" to address,
                "count" to info.first,
                "last" to info.second,
                "enabled" to selected.containsKey(address),
                "supported" to supported.contains(address.trim().lowercase()),
            )
        }
    }

    fun messages(context: Context, sender: String): List<Map<String, Any>> {
        val store = Store.get(context)
        val uid = Prefs(context).deviceUid
        val enabledAt = store.selectedSenders()[sender]
        val rows = mutableListOf<Map<String, Any>>()
        context.contentResolver.query(
            uri, arrayOf("body", "date", "date_sent"), "address = ?", arrayOf(sender), "date DESC LIMIT 100"
        )?.use {
            while (it.moveToNext()) {
                val body = it.getString(0) ?: continue
                val date = it.getLong(1)
                val sent = it.getLong(2).takeIf { value -> value > 0 } ?: date
                val state = store.stateOf(messageId(uid, sender, sent, body))
                    ?: if (enabledAt == null) "not_selected" else if (date < enabledAt) "before_selection" else "waiting"
                rows.add(mapOf("body" to body, "time" to date, "state" to state))
            }
        }
        return rows
    }

    /** Picks up anything the broadcast missed, such as an SMS that arrived while Android had the app stopped. */
    fun rescan(context: Context) {
        val store = Store.get(context)
        val uid = Prefs(context).deviceUid
        val oldest = System.currentTimeMillis() - RESCAN_WINDOW
        store.selectedSenders().forEach { (sender, enabledAt) ->
            context.contentResolver.query(
                uri, arrayOf("body", "date", "date_sent"), "address = ? AND date >= ?",
                arrayOf(sender, maxOf(enabledAt, oldest).toString()), "date ASC"
            )?.use {
                while (it.moveToNext()) {
                    val body = it.getString(0) ?: continue
                    val date = it.getLong(1)
                    val sent = it.getLong(2).takeIf { value -> value > 0 } ?: date
                    store.enqueue(messageId(uid, sender, sent, body), sender, body, sent, date, 0)
                }
            }
        }
    }
}

class ApiError(message: String) : Exception(message)

class Api(private val context: Context) {
    private val prefs = Prefs(context)

    private fun request(site: String, action: String, body: String, signed: Boolean): Pair<Int, JSONObject> {
        val connection = URL("$site/device-api.php?action=$action").openConnection() as HttpURLConnection
        try {
            connection.requestMethod = "POST"
            connection.connectTimeout = 20000
            connection.readTimeout = 30000
            connection.doOutput = true
            connection.setRequestProperty("Content-Type", "application/json")
            connection.setRequestProperty("User-Agent", "SmsForwarder/1.0 Android/${Build.VERSION.SDK_INT}")
            if (signed) {
                val timestamp = (System.currentTimeMillis() / 1000).toString()
                val uid = prefs.deviceUid
                connection.setRequestProperty("X-Device-Id", uid)
                connection.setRequestProperty("X-Timestamp", timestamp)
                connection.setRequestProperty("X-Signature", Crypto.sign("$uid\n$timestamp\n${sha256Hex(body)}"))
            }
            connection.outputStream.use { it.write(body.toByteArray(Charsets.UTF_8)) }
            val code = connection.responseCode
            val stream = if (code < 400) connection.inputStream else connection.errorStream
            val text = stream?.bufferedReader()?.use { it.readText() } ?: "{}"
            val json = try {
                JSONObject(text)
            } catch (e: Exception) {
                throw ApiError("The website sent an unexpected reply (HTTP $code)")
            }
            return Pair(code, json)
        } finally {
            connection.disconnect()
        }
    }

    fun pair(site: String, code: String) {
        val publicKey = Crypto.newSigningKey()
        val body = JSONObject().apply {
            put("code", code)
            put("device_uid", prefs.deviceUid)
            put("public_key", publicKey)
            put("name", "${Build.MANUFACTURER} ${Build.MODEL}")
        }
        val (status, reply) = request(site, "pair", body.toString(), false)
        if (status != 200) throw ApiError(reply.optString("message", "Pairing failed"))
        prefs.site = site
        prefs.matchCode = Crypto.matchCode(publicKey)
        prefs.deviceStatus = "pending"
        prefs.lastError = ""
    }

    fun refreshStatus(): String {
        val (status, reply) = request(prefs.site, "status", "{}", true)
        if (status == 200) {
            prefs.deviceStatus = reply.getString("device_status")
            prefs.supported = reply.getJSONArray("supported_senders").toString()
            prefs.lastError = ""
        } else if (reply.optString("device_status") == "revoked") {
            prefs.deviceStatus = "revoked"
        } else {
            throw ApiError(reply.optString("message", "HTTP $status"))
        }
        return prefs.deviceStatus
    }

    /** Sends until the queue is empty. Rows only leave the queue once the server has answered for them. */
    fun flush() {
        val store = Store.get(context)
        while (true) {
            val batch = store.pending(25)
            if (batch.length() == 0) return
            val (status, reply) = try {
                request(prefs.site, "sms", JSONObject().put("messages", batch).toString(), true)
            } catch (e: Exception) {
                store.markAllFailed("No connection, will retry")
                throw e
            }
            if (status != 200) {
                val message = reply.optString("message", "HTTP $status")
                store.markAllFailed(message)
                throw ApiError(message)
            }
            val results = reply.getJSONArray("results")
            for (i in 0 until results.length()) {
                val result = results.getJSONObject(i)
                val state = when (result.getString("result")) {
                    "forwarded" -> "forwarded"
                    "duplicate" -> "duplicate"
                    else -> "rejected"
                }
                store.mark(result.getString("id"), state, result.optString("reason"))
            }
        }
    }
}

object Sync {
    private val network = Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build()

    fun now(context: Context) {
        val work = OneTimeWorkRequestBuilder<SyncWorker>()
            .setConstraints(network)
            .setBackoffCriteria(BackoffPolicy.EXPONENTIAL, 30, TimeUnit.SECONDS)
            .build()
        WorkManager.getInstance(context).enqueueUniqueWork("sync_now", ExistingWorkPolicy.APPEND_OR_REPLACE, work)
    }

    fun schedule(context: Context) {
        val work = PeriodicWorkRequestBuilder<SyncWorker>(15, TimeUnit.MINUTES).setConstraints(network).build()
        WorkManager.getInstance(context).enqueueUniquePeriodicWork("sync_periodic", ExistingPeriodicWorkPolicy.KEEP, work)
    }
}
