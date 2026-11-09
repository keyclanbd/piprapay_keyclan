package com.keyclan.sms_forwarder

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.provider.Telephony
import androidx.work.CoroutineWorker
import androidx.work.WorkerParameters
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

class SyncWorker(context: Context, params: WorkerParameters) : CoroutineWorker(context, params) {
    override suspend fun doWork(): Result = withContext(Dispatchers.IO) {
        val prefs = Prefs(applicationContext)
        if (prefs.site.isEmpty()) return@withContext Result.success()
        try {
            Inbox.rescan(applicationContext)
            val api = Api(applicationContext)
            if (api.refreshStatus() == "active") api.flush()
            Result.success()
        } catch (e: Exception) {
            prefs.lastError = e.message ?: "Sync failed"
            Result.retry()
        }
    }
}

class SmsReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action != Telephony.Sms.Intents.SMS_RECEIVED_ACTION) return
        val parts = Telephony.Sms.Intents.getMessagesFromIntent(intent) ?: return
        val first = parts.firstOrNull() ?: return
        val sender = first.originatingAddress ?: return

        val store = Store.get(context)
        if (!store.selectedSenders().containsKey(sender)) return

        val slot = intent.getIntExtra("android.telephony.extra.SLOT_INDEX", intent.getIntExtra("slot", -1))
        val body = parts.joinToString("") { it.messageBody ?: "" }
        val sent = first.timestampMillis
        store.enqueue(
            Inbox.messageId(Prefs(context).deviceUid, sender, sent, body),
            sender, body, sent, System.currentTimeMillis(), slot + 1
        )
        Sync.now(context)
    }
}
