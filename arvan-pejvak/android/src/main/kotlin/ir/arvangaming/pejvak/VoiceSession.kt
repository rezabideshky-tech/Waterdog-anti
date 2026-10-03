package ir.arvangaming.pejvak

import android.content.Context
import com.twilio.audioswitch.AudioDevice
import io.livekit.android.LiveKit
import io.livekit.android.RoomOptions
import io.livekit.android.room.Room
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL

internal data class VoiceGrant(
    val livekitUrl: String,
    val token: String,
    val controlToken: String,
    val displayName: String,
    val expiresAt: Long,
)

internal data class NearbyPlayer(
    val name: String,
    val distance: Double,
)

/** Owns the LiveKit audio room for the foreground app/service lifetime. */
internal object VoiceSession {
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Main.immediate)
    @Volatile private var room: Room? = null
    @Volatile private var grant: VoiceGrant? = null
    @Volatile private var speakerOn = true
    @Volatile private var globallyMuted = false

    val isConnected: Boolean
        get() = room?.let { it.state != Room.State.DISCONNECTED } ?: false
    val remoteParticipantCount: Int get() = room?.remoteParticipants?.size ?: 0
    val currentGrant: VoiceGrant? get() = grant
    val isSpeakerOn: Boolean get() = speakerOn
    val isMuted: Boolean get() = globallyMuted

    suspend fun connect(context: Context, gatewayUrl: String, code: String): VoiceGrant {
        disconnect()
        val issued = withContext(Dispatchers.IO) { exchangeCode(gatewayUrl, code) }
        grant = issued
        var newRoom: Room? = null
        try {
            val createdRoom = LiveKit.create(context.applicationContext, RoomOptions())
            newRoom = createdRoom
            createdRoom.connect(issued.livekitUrl, issued.token)
            createdRoom.localParticipant.setMicrophoneEnabled(false)
            room = createdRoom
            setSpeakerEnabled(speakerOn)
            return issued
        } catch (error: Throwable) {
            try { newRoom?.disconnect() } catch (_: Throwable) { }
            disconnect(gatewayUrl)
            throw error
        }
    }

    suspend fun disconnect(gatewayUrl: String? = null) {
        val oldRoom = room
        val oldGrant = grant
        room = null
        grant = null
        if (oldRoom != null) {
            try {
                oldRoom.localParticipant.setMicrophoneEnabled(false)
            } catch (_: Throwable) {
                // The track may already be gone after a network disconnect.
            }
            try {
                oldRoom.disconnect()
            } catch (_: Throwable) {
                // A broken network must not strand the UI in a connected state.
            }
        }
        if (!gatewayUrl.isNullOrBlank() && oldGrant != null) {
            withContext(Dispatchers.IO) {
                try {
                    val url = URL(gatewayUrl.trimEnd('/') + "/v1/mobile/session")
                    (url.openConnection() as HttpURLConnection).run {
                        requestMethod = "DELETE"
                        connectTimeout = 5_000
                        readTimeout = 5_000
                        setRequestProperty("Authorization", "Bearer ${oldGrant.controlToken}")
                        responseCode
                        disconnect()
                    }
                } catch (_: Throwable) {
                    // Local disconnect succeeds even if the control gateway is unreachable.
                }
            }
        }
    }

    fun setMicrophoneEnabled(enabled: Boolean) {
        scope.launch {
            try {
                room?.localParticipant?.setMicrophoneEnabled(enabled && !globallyMuted)
            } catch (_: Throwable) {
                // The next explicit user action can retry; don't crash the overlay service.
            }
        }
    }

    fun setMuted(muted: Boolean) {
        globallyMuted = muted
        if (muted) setMicrophoneEnabled(false)
    }

    fun setPushToTalk(pressed: Boolean) {
        setMicrophoneEnabled(pressed && !globallyMuted)
    }

    fun setSpeakerEnabled(enabled: Boolean) {
        speakerOn = enabled
        val audioHandler = room?.audioSwitchHandler ?: return
        audioHandler.preferredDeviceList = if (enabled) {
            listOf(
                AudioDevice.BluetoothHeadset::class.java,
                AudioDevice.WiredHeadset::class.java,
                AudioDevice.Speakerphone::class.java,
                AudioDevice.Earpiece::class.java,
            )
        } else {
            listOf(
                AudioDevice.BluetoothHeadset::class.java,
                AudioDevice.WiredHeadset::class.java,
                AudioDevice.Earpiece::class.java,
                AudioDevice.Speakerphone::class.java,
            )
        }
    }

    suspend fun fetchNearby(gatewayUrl: String): Pair<String, List<NearbyPlayer>> = withContext(Dispatchers.IO) {
        val activeGrant = grant ?: return@withContext "برای دیدن بازیکنان، ابتدا وصل شو." to emptyList()
        val url = URL(gatewayUrl.trimEnd('/') + "/v1/mobile/nearby")
        val connection = url.openConnection() as HttpURLConnection
        try {
            connection.requestMethod = "GET"
            connection.connectTimeout = 7_000
            connection.readTimeout = 7_000
            connection.setRequestProperty("Authorization", "Bearer ${activeGrant.controlToken}")
            val code = connection.responseCode
            val stream = if (code in 200..299) connection.inputStream else connection.errorStream
            val body = stream?.bufferedReader()?.use { it.readText() }.orEmpty()
            if (code !in 200..299) throw IllegalStateException("ارتباط با سرویس پژواک خطا داد ($code).")
            val root = JSONObject(body)
            val list = mutableListOf<NearbyPlayer>()
            val array = root.optJSONArray("players")
            if (array != null) {
                for (index in 0 until array.length()) {
                    val player = array.optJSONObject(index) ?: continue
                    list += NearbyPlayer(
                        name = player.optString("name", "بازیکن"),
                        distance = player.optDouble("distance", -1.0),
                    )
                }
            }
            val state = when (root.optString("state")) {
                "waiting_for_game_presence" -> "برای نمایش بازیکنان، داخل سرور بازی بمان و چند لحظه صبر کن."
                else -> if (list.isEmpty()) "بازیکن نزدیکی نیست؛ وقتی کسی نزدیک شود اینجا می‌بینی." else ""
            }
            state to list
        } finally {
            connection.disconnect()
        }
    }

    private fun exchangeCode(gatewayUrl: String, rawCode: String): VoiceGrant {
        val normalizedBase = gatewayUrl.trim().trimEnd('/')
        require(normalizedBase.startsWith("https://", ignoreCase = true)) {
            "نشانی درگاه باید با https:// شروع شود."
        }
        val url = URL("$normalizedBase/v1/mobile/exchange")
        val connection = url.openConnection() as HttpURLConnection
        try {
            connection.requestMethod = "POST"
            connection.connectTimeout = 10_000
            connection.readTimeout = 12_000
            connection.doOutput = true
            connection.setRequestProperty("Content-Type", "application/json; charset=utf-8")
            val payload = JSONObject().put("code", rawCode.trim().uppercase()).toString().toByteArray(Charsets.UTF_8)
            connection.outputStream.use { it.write(payload) }
            val status = connection.responseCode
            val stream = if (status in 200..299) connection.inputStream else connection.errorStream
            val body = stream?.bufferedReader()?.use { it.readText() }.orEmpty()
            if (status !in 200..299) {
                val reason = try { JSONObject(body).optString("error") } catch (_: Throwable) { "" }
                throw IllegalStateException(when (reason) {
                    "code_expired_or_missing" -> "کد منقضی شده یا پیدا نشد؛ از داخل بازی کد تازه بگیر."
                    "rate_limited" -> "تعداد تلاش‌ها زیاد شد؛ کمی صبر کن و دوباره امتحان کن."
                    else -> "اتصال امن به سرور پژواک برقرار نشد ($status)."
                })
            }
            val json = JSONObject(body)
            val livekitUrl = json.getString("livekitUrl")
            val token = json.getString("token")
            val control = json.getString("controlToken")
            require(livekitUrl.startsWith("wss://", ignoreCase = true)) { "نشانی صدای امن سرور درست تنظیم نشده است." }
            require(token.isNotBlank() && control.isNotBlank()) { "پاسخ سرور ناقص است." }
            return VoiceGrant(
                livekitUrl = livekitUrl,
                token = token,
                controlToken = control,
                displayName = json.optString("displayName", "بازیکن"),
                expiresAt = json.optLong("expiresAt", 0L),
            )
        } finally {
            connection.disconnect()
        }
    }
}
