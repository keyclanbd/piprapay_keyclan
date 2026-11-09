package com.keyclan.sms_forwarder

import android.Manifest
import android.content.Intent
import android.content.pm.ApplicationInfo
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Bundle
import android.os.PowerManager
import android.provider.Settings
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodCall
import io.flutter.plugin.common.MethodChannel
import java.util.concurrent.Executors

class MainActivity : FlutterActivity() {
    private val background = Executors.newSingleThreadExecutor()

    private fun hasSmsPermission() = listOf(Manifest.permission.READ_SMS, Manifest.permission.RECEIVE_SMS)
        .all { checkSelfPermission(it) == PackageManager.PERMISSION_GRANTED }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        Sync.schedule(this)
        Sync.now(this)
    }

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, "forwarder").setMethodCallHandler { call, result ->
            background.execute {
                try {
                    val value = handle(call)
                    runOnUiThread { result.success(value) }
                } catch (e: Exception) {
                    runOnUiThread { result.error("failed", e.message ?: "Something went wrong", null) }
                }
            }
        }
    }

    private fun handle(call: MethodCall): Any? {
        val prefs = Prefs(this)
        val store = Store.get(this)
        return when (call.method) {
            "state" -> mapOf(
                "site" to prefs.site,
                "status" to prefs.deviceStatus,
                "matchCode" to prefs.matchCode,
                "lastError" to prefs.lastError,
                "counts" to store.counts(),
                "hasSms" to hasSmsPermission(),
                "batteryExempt" to (getSystemService(POWER_SERVICE) as PowerManager).isIgnoringBatteryOptimizations(packageName),
            )
            "requestSms" -> requestPermissions(arrayOf(Manifest.permission.READ_SMS, Manifest.permission.RECEIVE_SMS), 1)
            "requestBattery" -> startActivity(
                Intent(Settings.ACTION_REQUEST_IGNORE_BATTERY_OPTIMIZATIONS, Uri.parse("package:$packageName"))
            )
            "pair" -> {
                val site = call.argument<String>("site")!!.trim().trimEnd('/')
                val debuggable = applicationInfo.flags and ApplicationInfo.FLAG_DEBUGGABLE != 0
                val allowed = if (debuggable) site.startsWith("https://") || site.startsWith("http://10.0.2.2") else site == "https://pay.keyclan.com"
                if (!allowed) throw ApiError("This app can only connect to pay.keyclan.com")
                Api(this).pair(site, call.argument<String>("code")!!)
            }
            "refresh" -> if (prefs.site.isNotEmpty()) Api(this).refreshStatus() else null
            "senders" -> Inbox.senders(this)
            "messages" -> Inbox.messages(this, call.argument<String>("sender")!!)
            "setSender" -> {
                store.setSender(call.argument<String>("sender")!!, call.argument<Boolean>("enabled")!!)
                Sync.now(this)
            }
            "history" -> store.history(call.argument<String>("state"))
            "syncNow" -> Sync.now(this)
            "unpair" -> {
                store.clear()
                prefs.clear()
            }
            else -> throw IllegalArgumentException("Unknown method ${call.method}")
        }.let { if (it == Unit) null else it }
    }
}
