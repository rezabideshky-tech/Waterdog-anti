package ir.arvangaming.pejvak

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.graphics.Color
import android.graphics.PixelFormat
import android.graphics.drawable.GradientDrawable
import android.os.Build
import android.os.IBinder
import android.provider.Settings
import android.view.Gravity
import android.view.MotionEvent
import android.view.View
import android.view.WindowManager
import android.widget.FrameLayout
import android.widget.TextView
import androidx.core.app.NotificationCompat
import androidx.core.app.ServiceCompat
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch

class VoiceCallService : Service() {
    private val serviceScope = CoroutineScope(SupervisorJob() + Dispatchers.Main.immediate)
    private var windowManager: WindowManager? = null
    private var overlay: FrameLayout? = null
    private var overlayLabel: TextView? = null
    private var layoutParams: WindowManager.LayoutParams? = null
    private var gatewayUrl: String = ""
    private var pressStartX = 0f
    private var pressStartY = 0f
    private var originX = 0
    private var originY = 0
    private var didDrag = false
    private var micPressed = false

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        gatewayUrl = intent?.getStringExtra(EXTRA_GATEWAY).orEmpty().ifBlank { gatewayUrl }
        if (intent?.action != ACTION_HIDE_OVERLAY) startAsForeground()
        when (intent?.action) {
            ACTION_SHOW_OVERLAY -> showOverlay()
            ACTION_HIDE_OVERLAY -> removeOverlay()
            ACTION_DISCONNECT -> disconnectAndStop()
            else -> Unit
        }
        return START_NOT_STICKY
    }

    private fun startAsForeground() {
        val channelId = "arvan_pejvak_voice"
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val manager = getSystemService(NotificationManager::class.java)
            manager.createNotificationChannel(
                NotificationChannel(channelId, "تماس صوتی آروان پژواک", NotificationManager.IMPORTANCE_LOW)
                    .apply { description = "اعلان فعال‌بودن گفت‌وگوی صوتی" },
            )
        }
        val disconnectIntent = Intent(this, VoiceCallService::class.java).apply { action = ACTION_DISCONNECT }
        val pending = PendingIntent.getService(
            this,
            31,
            disconnectIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )
        val notification: Notification = NotificationCompat.Builder(this, channelId)
            .setSmallIcon(android.R.drawable.ic_btn_speak_now)
            .setContentTitle("آروان پژواک فعال است")
            .setContentText("برای صحبت، دکمهٔ شناور را نگه‌دار.")
            .setOngoing(true)
            .setCategory(NotificationCompat.CATEGORY_SERVICE)
            .addAction(0, "قطع اتصال", pending)
            .build()
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
            ServiceCompat.startForeground(
                this,
                NOTIFICATION_ID,
                notification,
                android.content.pm.ServiceInfo.FOREGROUND_SERVICE_TYPE_MICROPHONE,
            )
        } else {
            startForeground(NOTIFICATION_ID, notification)
        }
    }

    private fun showOverlay() {
        if (!Settings.canDrawOverlays(this) || overlay != null) return
        windowManager = getSystemService(Context.WINDOW_SERVICE) as WindowManager
        val size = dp(66)
        val container = FrameLayout(this).apply {
            background = GradientDrawable(
                GradientDrawable.Orientation.TL_BR,
                intArrayOf(0xff9b72f5.toInt(), 0xff6d3dd1.toInt()),
            ).apply {
                shape = GradientDrawable.OVAL
                setStroke(dp(2), 0x88ffffff.toInt())
            }
            elevation = dp(8).toFloat()
        }
        val label = TextView(this).apply {
            text = "🎙"
            textSize = 25f
            gravity = Gravity.CENTER
            setTextColor(Color.WHITE)
            contentDescription = "برای صحبت نگه‌دار"
        }
        container.addView(label, FrameLayout.LayoutParams(-1, -1))
        val type = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY
        } else {
            @Suppress("DEPRECATION")
            WindowManager.LayoutParams.TYPE_PHONE
        }
        val params = WindowManager.LayoutParams(
            size,
            size,
            type,
            WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE or WindowManager.LayoutParams.FLAG_NOT_TOUCH_MODAL,
            PixelFormat.TRANSLUCENT,
        ).apply {
            gravity = Gravity.TOP or Gravity.END
            x = dp(16)
            y = dp(230)
        }
        container.setOnTouchListener { view, event ->
            when (event.actionMasked) {
                MotionEvent.ACTION_DOWN -> {
                    didDrag = false
                    pressStartX = event.rawX
                    pressStartY = event.rawY
                    originX = params.x
                    originY = params.y
                    if (VoiceSession.isMuted) {
                        micPressed = false
                        label.text = "🔇"
                        android.widget.Toast.makeText(this, "میکروفن خاموش است.", android.widget.Toast.LENGTH_SHORT).show()
                    } else {
                        micPressed = true
                        VoiceSession.setPushToTalk(true)
                        label.text = "🔴"
                    }
                    view.animate().scaleX(0.92f).scaleY(0.92f).setDuration(90).start()
                    true
                }
                MotionEvent.ACTION_MOVE -> {
                    val dx = event.rawX - pressStartX
                    val dy = event.rawY - pressStartY
                    if (kotlin.math.abs(dx) > dp(5) || kotlin.math.abs(dy) > dp(5)) didDrag = true
                    if (didDrag) {
                        params.x = originX - dx.toInt()
                        params.y = originY + dy.toInt()
                        try { windowManager?.updateViewLayout(view, params) } catch (_: Throwable) { }
                    }
                    true
                }
                MotionEvent.ACTION_UP, MotionEvent.ACTION_CANCEL -> {
                    if (micPressed) VoiceSession.setPushToTalk(false)
                    micPressed = false
                    label.text = "🎙"
                    view.animate().scaleX(1f).scaleY(1f).setDuration(120).start()
                    true
                }
                else -> false
            }
        }
        overlay = container
        overlayLabel = label
        layoutParams = params
        try {
            windowManager?.addView(container, params)
        } catch (_: Throwable) {
            overlay = null
            overlayLabel = null
            layoutParams = null
        }
    }

    private fun removeOverlay() {
        val view = overlay ?: return
        if (micPressed) VoiceSession.setPushToTalk(false)
        micPressed = false
        try { windowManager?.removeView(view) } catch (_: Throwable) { }
        overlay = null
        overlayLabel = null
        layoutParams = null
    }

    private fun disconnectAndStop() {
        removeOverlay()
        serviceScope.launch {
            VoiceSession.disconnect(gatewayUrl)
            stopForeground(STOP_FOREGROUND_REMOVE)
            stopSelf()
        }
    }

    override fun onDestroy() {
        removeOverlay()
        super.onDestroy()
    }

    private fun dp(value: Int): Int = (value * resources.displayMetrics.density).toInt()

    companion object {
        const val ACTION_START = "ir.arvangaming.pejvak.START"
        const val ACTION_SHOW_OVERLAY = "ir.arvangaming.pejvak.SHOW_OVERLAY"
        const val ACTION_HIDE_OVERLAY = "ir.arvangaming.pejvak.HIDE_OVERLAY"
        const val ACTION_DISCONNECT = "ir.arvangaming.pejvak.DISCONNECT"
        const val EXTRA_GATEWAY = "gateway_url"
        private const val NOTIFICATION_ID = 6207

        fun start(context: Context, gatewayUrl: String) {
            val intent = Intent(context, VoiceCallService::class.java).apply {
                action = ACTION_START
                putExtra(EXTRA_GATEWAY, gatewayUrl)
            }
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) context.startForegroundService(intent)
            else context.startService(intent)
        }

        fun showOverlay(context: Context) {
            context.startService(Intent(context, VoiceCallService::class.java).apply {
                action = ACTION_SHOW_OVERLAY
            })
        }

        fun hideOverlay(context: Context) {
            context.startService(Intent(context, VoiceCallService::class.java).apply {
                action = ACTION_HIDE_OVERLAY
            })
        }

        fun stop(context: Context) {
            context.startService(Intent(context, VoiceCallService::class.java).apply {
                action = ACTION_DISCONNECT
            })
        }
    }
}
