package ir.arvangaming.pejvak

import android.Manifest
import android.app.Activity
import android.content.Context
import android.content.Intent
import android.content.SharedPreferences
import android.content.pm.PackageManager
import android.graphics.Color
import android.graphics.Typeface
import android.graphics.drawable.GradientDrawable
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.provider.Settings
import android.view.Gravity
import android.view.HapticFeedbackConstants
import android.view.MotionEvent
import android.view.View
import android.view.ViewGroup
import android.view.WindowManager
import android.view.animation.DecelerateInterpolator
import android.widget.EditText
import android.widget.FrameLayout
import android.widget.LinearLayout
import android.widget.ScrollView
import android.widget.TextView
import android.widget.Toast
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.cancel
import kotlinx.coroutines.delay
import kotlinx.coroutines.launch
import org.json.JSONObject

/** Persian, touch-first client for the Arvan Pejvak proximity-voice network. */
class MainActivity : Activity() {
    private val uiScope = CoroutineScope(SupervisorJob() + Dispatchers.Main.immediate)
    private val handler = Handler(Looper.getMainLooper())
    private lateinit var preferences: SharedPreferences
    private lateinit var pageHost: FrameLayout
    private lateinit var navigationBar: LinearLayout
    private lateinit var codeInput: EditText
    private lateinit var gatewayInput: EditText
    private lateinit var statusDot: View
    private lateinit var statusTitle: TextView
    private lateinit var statusSubtitle: TextView
    private lateinit var peopleContainer: LinearLayout
    private lateinit var peopleHint: TextView
    private lateinit var overlaySwitch: android.widget.Switch
    private var currentPage = Page.VOICE
    private var state = ConnectionState.OFFLINE
    private var nearbyPlayers: List<NearbyPlayer> = emptyList()
    private var nearbyHint = "بعد از اتصال، بازیکنان نزدیک اینجا نمایش داده می‌شوند."
    private var gatewayUrl = ""
    private var currentCode = ""
    private var micMuted = false
    private var speakerEnabled = true
    private var overlayWanted = false
    private var pollJobRunning = false
    private var statusPulse: android.animation.AnimatorSet? = null

    private val nearbyPoll = object : Runnable {
        override fun run() {
            if (state != ConnectionState.CONNECTED || isFinishing) return
            if (!VoiceSession.isConnected) {
                state = ConnectionState.OFFLINE
                nearbyPlayers = emptyList()
                nearbyHint = "اتصال صوتی قطع شد؛ دوباره کد بگیر و وصل شو."
                overlayWanted = false
                preferences.edit().putBoolean(KEY_OVERLAY, false).apply()
                if (!isFinishing) renderPage()
                showToast(nearbyHint)
                return
            }
            pollNearby()
            handler.postDelayed(this, 2_500L)
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        preferences = getSharedPreferences("arvan_pejvak", Context.MODE_PRIVATE)
        gatewayUrl = preferences.getString(KEY_GATEWAY, "")?.trim().orEmpty()
        overlayWanted = preferences.getBoolean(KEY_OVERLAY, false)
        if (VoiceSession.isConnected) {
            state = ConnectionState.CONNECTED
            micMuted = VoiceSession.isMuted
            speakerEnabled = VoiceSession.isSpeakerOn
        }
        window.statusBarColor = DEEP
        window.navigationBarColor = BG
        window.decorView.systemUiVisibility = View.SYSTEM_UI_FLAG_LIGHT_NAVIGATION_BAR
        window.setSoftInputMode(WindowManager.LayoutParams.SOFT_INPUT_ADJUST_RESIZE)
        buildShell()
        renderPage()
        updateConnectionStatus()
    }

    override fun onResume() {
        super.onResume()
        if (VoiceSession.isConnected) {
            state = ConnectionState.CONNECTED
            micMuted = VoiceSession.isMuted
            speakerEnabled = VoiceSession.isSpeakerOn
        }
        updateConnectionStatus()
        if (overlayWanted && state == ConnectionState.CONNECTED && Settings.canDrawOverlays(this)) {
            VoiceCallService.showOverlay(this)
            if (::overlaySwitch.isInitialized) overlaySwitch.isChecked = true
        }
    }

    override fun onPause() {
        super.onPause()
        statusPulse?.cancel()
        statusPulse = null
    }

    override fun onDestroy() {
        handler.removeCallbacks(nearbyPoll)
        statusPulse?.cancel()
        if (state == ConnectionState.CONNECTING) VoiceCallService.stop(this)
        uiScope.cancel()
        super.onDestroy()
    }

    @Deprecated("Deprecated in Android API, retained for the minimum supported platform")
    override fun onBackPressed() {
        if (currentPage != Page.VOICE) navigate(Page.VOICE) else super.onBackPressed()
    }

    private fun buildShell() {
        val shell = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            layoutDirection = View.LAYOUT_DIRECTION_RTL
            setBackgroundColor(BG)
        }
        pageHost = FrameLayout(this)
        shell.addView(pageHost, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, 0, 1f))
        navigationBar = buildNavigation()
        shell.addView(navigationBar, LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(76)))
        setContentView(shell)
    }

    private fun buildNavigation(): LinearLayout {
        val bar = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            layoutDirection = View.LAYOUT_DIRECTION_RTL
            gravity = Gravity.CENTER
            setPadding(dp(9), dp(8), dp(9), dp(8))
            background = shape(SURFACE, 26, BORDER)
            elevation = dp(10).toFloat()
        }
        val params = LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(62)).apply {
            setMargins(dp(16), 0, dp(16), dp(8))
        }
        val tabs = listOf(
            Page.VOICE to ("◉" to "گفت‌وگو"),
            Page.NEARBY to ("⌖" to "بازیکنان"),
            Page.SETTINGS to ("⚙" to "تنظیمات"),
        )
        tabs.forEach { (page, labels) ->
            val item = LinearLayout(this).apply {
                orientation = LinearLayout.HORIZONTAL
                gravity = Gravity.CENTER
                setPadding(dp(8), dp(7), dp(8), dp(7))
                tag = page
                background = shape(if (currentPage == page) LAVENDER_WASH else Color.TRANSPARENT, 20, Color.TRANSPARENT)
                addView(text(labels.first, 17, if (currentPage == page) PURPLE else MUTED, true))
                addView(text(labels.second, 11, if (currentPage == page) DEEP else MUTED, true).apply {
                    setPadding(dp(6), 0, 0, 0)
                })
                setOnClickListener { navigate(page) }
            }
            bar.addView(item, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.MATCH_PARENT, 1f))
        }
        return bar.apply { layoutParams = params }
    }

    private fun navigate(page: Page) {
        currentPage = page
        updateNavigationSelection()
        renderPage()
    }

    private fun updateNavigationSelection() {
        if (!::navigationBar.isInitialized) return
        for (index in 0 until navigationBar.childCount) {
            val item = navigationBar.getChildAt(index) as? LinearLayout ?: continue
            val selected = item.tag == currentPage
            item.background = shape(if (selected) LAVENDER_WASH else Color.TRANSPARENT, 20, Color.TRANSPARENT)
            (item.getChildAt(0) as? TextView)?.setTextColor(if (selected) PURPLE else MUTED)
            (item.getChildAt(1) as? TextView)?.setTextColor(if (selected) DEEP else MUTED)
        }
    }

    private fun renderPage() {
        statusPulse?.cancel()
        statusPulse = null
        pageHost.removeAllViews()
        val content = when (currentPage) {
            Page.VOICE -> buildVoicePage()
            Page.NEARBY -> buildNearbyPage()
            Page.SETTINGS -> buildSettingsPage()
        }
        val scroll = ScrollView(this).apply {
            isFillViewport = true
            isVerticalScrollBarEnabled = false
            clipToPadding = false
            setPadding(0, 0, 0, dp(12))
            addView(content, FrameLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT))
        }
        pageHost.addView(scroll, FrameLayout.LayoutParams(-1, -1))
        scroll.alpha = 0f
        scroll.translationY = dp(12).toFloat()
        scroll.animate().alpha(1f).translationY(0f).setDuration(300).setInterpolator(DecelerateInterpolator()).start()
        for (i in 0 until content.childCount) {
            val child = content.getChildAt(i)
            child.alpha = 0f
            child.translationY = dp(9).toFloat()
            child.animate().alpha(1f).translationY(0f).setStartDelay((i * 34L).coerceAtMost(250L))
                .setDuration(330).setInterpolator(DecelerateInterpolator()).start()
        }
        updateNavigationSelection()
        if (currentPage == Page.NEARBY) renderNearbyRows()
        if (currentPage == Page.SETTINGS) gatewayInput.setText(gatewayUrl)
        if (currentPage == Page.VOICE) updateConnectionStatus()
    }

    private fun newPage(): LinearLayout = LinearLayout(this).apply {
        orientation = LinearLayout.VERTICAL
        layoutDirection = View.LAYOUT_DIRECTION_RTL
        setPadding(dp(18), dp(14), dp(18), dp(24))
        setBackgroundColor(BG)
    }

    private fun addHeader(parent: LinearLayout, subtitle: String) {
        val header = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            layoutDirection = View.LAYOUT_DIRECTION_RTL
            gravity = Gravity.CENTER_VERTICAL
            setPadding(dp(17), dp(15), dp(17), dp(15))
            background = gradient(DEEP, PURPLE, 26)
            elevation = dp(5).toFloat()
        }
        val logo = text("پ", 25, Color.WHITE, true).apply {
            gravity = Gravity.CENTER
            background = shape(0x2affffff, 18, 0x66ffffff)
        }
        header.addView(logo, LinearLayout.LayoutParams(dp(47), dp(47)))
        val titles = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(0, 0, dp(11), 0)
            addView(text("آروان پژواک", 19, Color.WHITE, true))
            addView(text(subtitle, 11, 0xffeee7ff.toInt(), false).apply { setPadding(0, dp(3), 0, 0) })
        }
        header.addView(titles, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f))
        header.addView(text("●", 13, 0xffd6c2ff.toInt(), true).apply {
            contentDescription = "نسخهٔ آزمایشی"
        })
        parent.addView(header, matchWrap().apply { bottomMargin = dp(18) })
    }

    private fun buildVoicePage(): LinearLayout {
        val page = newPage()
        addHeader(page, "گفت‌وگوی نزدیک‌محور برای Minecraft Bedrock")
        page.addView(buildStatusCard(), matchWrap())
        page.addView(sectionGap(15))
        page.addView(buildConnectionCard(), matchWrap())
        page.addView(sectionGap(15))
        page.addView(buildVoiceControls(), matchWrap())
        page.addView(sectionGap(15))
        page.addView(buildNearbyPreview(), matchWrap())
        page.addView(sectionGap(12))
        page.addView(text("صدای واقعی فقط پس از اتصال امن به سرور پژواک پخش می‌شود.", 11, MUTED, false).apply {
            gravity = Gravity.CENTER
            setPadding(dp(8), dp(3), dp(8), dp(3))
        }, matchWrap())
        return page
    }

    private fun buildStatusCard(): View {
        val card = cardColumn().apply { setPadding(dp(19), dp(18), dp(19), dp(18)) }
        val row = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            layoutDirection = View.LAYOUT_DIRECTION_RTL
            gravity = Gravity.CENTER_VERTICAL
        }
        val halo = FrameLayout(this).apply {
            background = shape(LAVENDER_WASH, 26, 0xffe6dcfb.toInt())
        }
        statusDot = View(this).apply { background = shape(PURPLE, 50, Color.TRANSPARENT) }
        halo.addView(statusDot, FrameLayout.LayoutParams(dp(18), dp(18), Gravity.CENTER))
        row.addView(halo, LinearLayout.LayoutParams(dp(52), dp(52)))
        val labels = LinearLayout(this).apply {
            orientation = LinearLayout.VERTICAL
            setPadding(0, 0, dp(12), 0)
        }
        statusTitle = text("آمادهٔ اتصال", 20, INK, true)
        statusSubtitle = text("کد اتصال را از داخل بازی بگیر.", 12, MUTED, false).apply {
            setPadding(0, dp(4), 0, 0)
        }
        labels.addView(statusTitle)
        labels.addView(statusSubtitle)
        row.addView(labels, LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f))
        card.addView(row)
        card.addView(sectionGap(13))
        val badge = text("●  حریم خصوصی با کنترل خودت", 11, PURPLE, true).apply {
            background = shape(LAVENDER_WASH, 16, Color.TRANSPARENT)
            setPadding(dp(12), dp(8), dp(12), dp(8))
        }
        card.addView(badge, wrapWrap())
        updateConnectionStatus()
        return card
    }

    private fun buildConnectionCard(): View {
        val card = cardColumn().apply { setPadding(dp(18), dp(18), dp(18), dp(18)) }
        card.addView(text("کد اتصال بازی", 16, INK, true))
        card.addView(text("در بازی دستور /pejvak code را بزن و کد یک‌بارمصرف را وارد کن.", 11, MUTED, false).apply {
            setPadding(0, dp(6), 0, dp(13))
        })
        codeInput = EditText(this).apply {
            hint = "مثلاً 4K8P7M2X"
            textSize = 18f
            setTextColor(INK)
            setHintTextColor(0xff9b90aa.toInt())
            gravity = Gravity.CENTER
            layoutDirection = View.LAYOUT_DIRECTION_LTR
            textDirection = View.TEXT_DIRECTION_LTR
            inputType = android.text.InputType.TYPE_CLASS_TEXT or android.text.InputType.TYPE_TEXT_FLAG_CAP_CHARACTERS
            setSingleLine(true)
            filters = arrayOf(android.text.InputFilter.LengthFilter(8))
            background = shape(0xfffaf8ff.toInt(), 19, BORDER)
            setPadding(dp(14), dp(15), dp(14), dp(15))
            isEnabled = state != ConnectionState.CONNECTED
        }
        card.addView(codeInput, LinearLayout.LayoutParams(-1, dp(58)))
        card.addView(sectionGap(12))
        val action = if (state == ConnectionState.CONNECTED) "قطع اتصال" else when (state) {
            ConnectionState.CONNECTING -> "در حال اتصال…"
            else -> "ورود به گفت‌وگو"
        }
        val connect = primaryButton(action).apply {
            isEnabled = state != ConnectionState.CONNECTING
            alpha = if (isEnabled) 1f else 0.72f
            setOnClickListener {
                if (state == ConnectionState.CONNECTED) disconnectVoice() else beginConnect()
            }
        }
        card.addView(connect, LinearLayout.LayoutParams(-1, dp(58)))
        card.addView(text("کد فقط یک‌بار مصرف است و بعد از ۲ دقیقه باطل می‌شود.", 10, MUTED, false).apply {
            gravity = Gravity.CENTER
            setPadding(0, dp(11), 0, 0)
        }, matchWrap())
        return card
    }

    private fun buildVoiceControls(): View {
        val card = cardColumn().apply { setPadding(dp(18), dp(18), dp(18), dp(18)) }
        val top = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            layoutDirection = View.LAYOUT_DIRECTION_RTL
            gravity = Gravity.CENTER_VERTICAL
        }
        top.addView(text("کنترل صدا", 16, INK, true), LinearLayout.LayoutParams(0, -2, 1f))
        top.addView(text(if (state == ConnectionState.CONNECTED) "رمزگذاری‌شده" else "پس از اتصال فعال", 10,
            if (state == ConnectionState.CONNECTED) GREEN else MUTED, true).apply {
            background = shape(if (state == ConnectionState.CONNECTED) GREEN_WASH else LAVENDER_WASH, 14, Color.TRANSPARENT)
            setPadding(dp(9), dp(6), dp(9), dp(6))
        })
        card.addView(top)
        card.addView(sectionGap(14))
        val ptt = TextView(this).apply {
            text = "🎙\nبرای صحبت نگه‌دار"
            textSize = 17f
            setTextColor(Color.WHITE)
            typeface = Typeface.DEFAULT_BOLD
            gravity = Gravity.CENTER
            layoutDirection = View.LAYOUT_DIRECTION_RTL
            background = gradient(PURPLE, 0xff8b5cf6.toInt(), 25)
            elevation = dp(4).toFloat()
            alpha = if (state == ConnectionState.CONNECTED && !micMuted) 1f else 0.58f
            contentDescription = "برای ارسال صدای خود، دکمه را نگه‌دار"
            isEnabled = state == ConnectionState.CONNECTED
            setOnTouchListener { view, event ->
                if (state != ConnectionState.CONNECTED || micMuted) {
                    if (event.actionMasked == MotionEvent.ACTION_UP) showToast("ابتدا اتصال را برقرار و میکروفن را فعال کن.")
                    return@setOnTouchListener false
                }
                when (event.actionMasked) {
                    MotionEvent.ACTION_DOWN -> {
                        view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
                        view.animate().scaleX(0.97f).scaleY(0.97f).setDuration(90).start()
                        VoiceSession.setPushToTalk(true)
                        view.alpha = 1f
                        true
                    }
                    MotionEvent.ACTION_UP, MotionEvent.ACTION_CANCEL -> {
                        VoiceSession.setPushToTalk(false)
                        view.animate().scaleX(1f).scaleY(1f).setDuration(120).start()
                        true
                    }
                    else -> true
                }
            }
        }
        card.addView(ptt, LinearLayout.LayoutParams(-1, dp(100)))
        card.addView(sectionGap(13))
        val controls = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            layoutDirection = View.LAYOUT_DIRECTION_RTL
        }
        val micText = if (micMuted) "میکروفن خاموش" else "میکروفن آماده"
        controls.addView(secondaryButton("◉  $micText", if (micMuted) RED_WASH else LAVENDER_WASH) {
            if (state != ConnectionState.CONNECTED) {
                showToast("برای کنترل میکروفن، اول وصل شو.")
            } else {
                micMuted = !micMuted
                VoiceSession.setMuted(micMuted)
                renderPage()
            }
        }, LinearLayout.LayoutParams(0, dp(50), 1f))
        val gap = View(this)
        controls.addView(gap, LinearLayout.LayoutParams(dp(9), 1))
        controls.addView(secondaryButton(if (speakerEnabled) "◖  بلندگو" else "◖  گوشی", LAVENDER_WASH) {
            speakerEnabled = !speakerEnabled
            VoiceSession.setSpeakerEnabled(speakerEnabled)
            renderPage()
        }, LinearLayout.LayoutParams(0, dp(50), 1f))
        card.addView(controls)
        card.addView(sectionGap(13))
        val overlayRow = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            layoutDirection = View.LAYOUT_DIRECTION_RTL
            gravity = Gravity.CENTER_VERTICAL
            setPadding(dp(12), dp(7), dp(9), dp(7))
            background = shape(0xfffaf8ff.toInt(), 17, BORDER)
        }
        val overlayLabels = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL }
        overlayLabels.addView(text("دکمهٔ شناور روی Minecraft", 13, INK, true))
        overlayLabels.addView(text("برای صحبت بدون خروج از بازی", 10, MUTED, false).apply { setPadding(0, dp(3), 0, 0) })
        overlayRow.addView(overlayLabels, LinearLayout.LayoutParams(0, -2, 1f))
        overlaySwitch = android.widget.Switch(this).apply {
            isChecked = overlayWanted
            isEnabled = state == ConnectionState.CONNECTED
            setOnCheckedChangeListener { _, checked -> toggleOverlay(checked) }
        }
        overlayRow.addView(overlaySwitch)
        card.addView(overlayRow)
        return card
    }

    private fun buildNearbyPreview(): View {
        val card = cardColumn().apply { setPadding(dp(18), dp(17), dp(18), dp(15)) }
        val titleRow = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            layoutDirection = View.LAYOUT_DIRECTION_RTL
            gravity = Gravity.CENTER_VERTICAL
        }
        titleRow.addView(text("بازیکنان نزدیک", 15, INK, true), LinearLayout.LayoutParams(0, -2, 1f))
        titleRow.addView(text(if (state == ConnectionState.CONNECTED) "${nearbyPlayers.size} نفر" else "—", 11, PURPLE, true))
        card.addView(titleRow)
        peopleHint = text(nearbyHint, 11, MUTED, false).apply { setPadding(0, dp(10), 0, 0) }
        card.addView(peopleHint)
        peopleContainer = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL }
        card.addView(peopleContainer, matchWrap())
        renderNearbyRows()
        return card
    }

    private fun buildNearbyPage(): LinearLayout {
        val page = newPage()
        addHeader(page, "بازیکنانی که واقعاً در محدوده‌اند")
        val card = cardColumn().apply { setPadding(dp(18), dp(18), dp(18), dp(18)) }
        val row = LinearLayout(this).apply {
            orientation = LinearLayout.HORIZONTAL
            layoutDirection = View.LAYOUT_DIRECTION_RTL
            gravity = Gravity.CENTER_VERTICAL
        }
        row.addView(text("اطراف تو", 18, INK, true), LinearLayout.LayoutParams(0, -2, 1f))
        row.addView(secondaryButton("↻ تازه‌سازی", LAVENDER_WASH) { pollNearby() }, wrapWrap())
        card.addView(row)
        peopleHint = text(nearbyHint, 12, MUTED, false).apply { setPadding(0, dp(12), 0, dp(4)) }
        card.addView(peopleHint)
        peopleContainer = LinearLayout(this).apply { orientation = LinearLayout.VERTICAL }
        card.addView(peopleContainer, matchWrap())
        renderNearbyRows()
        page.addView(card, matchWrap())
        page.addView(sectionGap(14))
        page.addView(infoCard("صدای نزدیک‌محور", "فقط بازیکنانی که در همان دنیا و در فاصلهٔ تعیین‌شده هستند، صدایت را می‌شنوند. محدوده از تنظیمات سرور کنترل می‌شود."), matchWrap())
        return page
    }

    private fun buildSettingsPage(): LinearLayout {
        val page = newPage()
        addHeader(page, "تنظیم امن و تجربهٔ شخصی")
        val card = cardColumn().apply { setPadding(dp(18), dp(18), dp(18), dp(18)) }
        card.addView(text("نشانی درگاه پژواک", 16, INK, true))
        card.addView(text("این نشانی را مدیر سرور می‌دهد؛ ارتباط باید HTTPS باشد.", 11, MUTED, false).apply {
            setPadding(0, dp(6), 0, dp(12))
        })
        gatewayInput = EditText(this).apply {
            hint = "https://voice.example.com"
            setTextColor(INK)
            setHintTextColor(MUTED)
            textSize = 14f
            inputType = android.text.InputType.TYPE_CLASS_TEXT or android.text.InputType.TYPE_TEXT_VARIATION_URI
            layoutDirection = View.LAYOUT_DIRECTION_LTR
            textDirection = View.TEXT_DIRECTION_LTR
            setSingleLine(true)
            background = shape(0xfffaf8ff.toInt(), 18, BORDER)
            setPadding(dp(13), dp(14), dp(13), dp(14))
            isEnabled = state != ConnectionState.CONNECTED
        }
        card.addView(gatewayInput, LinearLayout.LayoutParams(-1, dp(55)))
        card.addView(sectionGap(12))
        card.addView(primaryButton("ذخیرهٔ تنظیمات") {
            val value = gatewayInput.text.toString().trim().trimEnd('/')
            if (value.isNotEmpty() && !value.startsWith("https://", true)) {
                showToast("نشانی باید با https:// شروع شود.")
            } else {
                gatewayUrl = value
                preferences.edit().putString(KEY_GATEWAY, gatewayUrl).apply()
                showToast(if (gatewayUrl.isEmpty()) "نشانی پاک شد." else "تنظیمات ذخیره شد.")
            }
        }.apply {
            isEnabled = state != ConnectionState.CONNECTED
            alpha = if (isEnabled) 1f else 0.55f
        }, LinearLayout.LayoutParams(-1, dp(54)))
        page.addView(card, matchWrap())
        page.addView(sectionGap(14))
        page.addView(infoCard("حریم خصوصی", "کد بازی کوتاه‌عمر و یک‌بارمصرف است. رمز Microsoft یا Xbox را وارد نکن؛ این برنامه برای اتصال فقط کد داخل بازی را می‌خواهد."), matchWrap())
        page.addView(sectionGap(12))
        page.addView(infoCard("کیفیت صدا", "صدای زنده با WebRTC و رمزگذاری انتقالی برقرار می‌شود. سرور صوتی باید با افزونهٔ PocketMine و این اپ روی یک درگاه امن هماهنگ باشد."), matchWrap())
        return page
    }

    private fun renderNearbyRows() {
        if (!::peopleContainer.isInitialized) return
        peopleContainer.removeAllViews()
        if (nearbyPlayers.isEmpty()) {
            peopleHint.text = when {
                state != ConnectionState.CONNECTED -> "بعد از اتصال امن و ورود به بازی، بازیکنان نزدیک نمایش داده می‌شوند."
                else -> nearbyHint
            }
            return
        }
        peopleHint.text = "بازیکنان داخل محدودهٔ صدای تو"
        nearbyPlayers.take(12).forEachIndexed { index, player ->
            val row = LinearLayout(this).apply {
                orientation = LinearLayout.HORIZONTAL
                layoutDirection = View.LAYOUT_DIRECTION_RTL
                gravity = Gravity.CENTER_VERTICAL
                setPadding(dp(10), dp(9), dp(10), dp(9))
                background = shape(if (index % 2 == 0) 0xfffaf8ff.toInt() else SURFACE, 16, BORDER)
            }
            val avatar = text(player.name.firstOrNull()?.toString() ?: "؟", 15, PURPLE, true).apply {
                gravity = Gravity.CENTER
                background = shape(LAVENDER_WASH, 19, Color.TRANSPARENT)
            }
            row.addView(avatar, LinearLayout.LayoutParams(dp(40), dp(40)))
            row.addView(text(player.name, 13, INK, true).apply { setPadding(dp(10), 0, 0, 0) },
                LinearLayout.LayoutParams(0, -2, 1f))
            row.addView(text("${player.distance.toInt()} بلوک", 10, MUTED, false))
            val params = matchWrap().apply { topMargin = dp(7) }
            peopleContainer.addView(row, params)
        }
    }

    private fun beginConnect() {
        currentCode = codeInput.text.toString().trim().uppercase()
        if (currentCode.length != 8) {
            showToast("کد ۸ حرفی را دقیق وارد کن.")
            codeInput.requestFocus()
            return
        }
        if (gatewayUrl.isBlank()) {
            showToast("اول نشانی امن درگاه را در تنظیمات وارد کن.")
            navigate(Page.SETTINGS)
            return
        }
        if (!gatewayUrl.startsWith("https://", true)) {
            showToast("درگاه صوتی باید HTTPS باشد.")
            return
        }
        if (checkSelfPermission(Manifest.permission.RECORD_AUDIO) != PackageManager.PERMISSION_GRANTED) {
            requestPermissions(arrayOf(Manifest.permission.RECORD_AUDIO), REQUEST_MIC)
            return
        }
        connectNow()
    }

    private fun connectNow() {
        state = ConnectionState.CONNECTING
        updateConnectionStatus()
        VoiceCallService.start(this, gatewayUrl)
        uiScope.launch {
            try {
                VoiceSession.connect(this@MainActivity, gatewayUrl, currentCode)
                state = ConnectionState.CONNECTED
                micMuted = false
                VoiceSession.setMuted(false)
                VoiceSession.setMicrophoneEnabled(false)
                navigate(Page.VOICE)
                if (overlayWanted && Settings.canDrawOverlays(this@MainActivity)) {
                    VoiceCallService.showOverlay(this@MainActivity)
                }
                handler.removeCallbacks(nearbyPoll)
                handler.post(nearbyPoll)
                showToast("وصل شدی؛ دکمهٔ صحبت را نگه‌دار.")
            } catch (error: Throwable) {
                if (error is CancellationException) throw error
                state = ConnectionState.OFFLINE
                nearbyPlayers = emptyList()
                nearbyHint = error.message ?: "اتصال برقرار نشد؛ کد و نشانی سرور را بررسی کن."
                VoiceCallService.stop(this@MainActivity)
                updateConnectionStatus()
                renderPage()
                showToast(nearbyHint)
            }
        }
    }

    private fun disconnectVoice() {
        handler.removeCallbacks(nearbyPoll)
        state = ConnectionState.OFFLINE
        nearbyPlayers = emptyList()
        nearbyHint = "بعد از اتصال، بازیکنان نزدیک اینجا نمایش داده می‌شوند."
        VoiceSession.setMicrophoneEnabled(false)
        VoiceCallService.stop(this)
        renderPage()
        showToast("از گفت‌وگوی صوتی خارج شدی.")
    }

    private fun pollNearby() {
        if (state != ConnectionState.CONNECTED || pollJobRunning || gatewayUrl.isBlank()) return
        pollJobRunning = true
        uiScope.launch {
            try {
                val result = VoiceSession.fetchNearby(gatewayUrl)
                nearbyHint = result.first
                nearbyPlayers = result.second
                if (::peopleContainer.isInitialized) renderNearbyRows()
            } catch (error: Throwable) {
                if (error is CancellationException) throw error
                nearbyHint = error.message ?: "فهرست بازیکنان موقتاً در دسترس نیست."
                nearbyPlayers = emptyList()
                if (::peopleContainer.isInitialized) renderNearbyRows()
            } finally {
                pollJobRunning = false
            }
        }
    }

    private fun toggleOverlay(checked: Boolean) {
        if (state != ConnectionState.CONNECTED) {
            overlaySwitch.isChecked = false
            showToast("برای استفاده از دکمهٔ شناور، ابتدا وصل شو.")
            return
        }
        if (checked && !Settings.canDrawOverlays(this)) {
            overlayWanted = true
            preferences.edit().putBoolean(KEY_OVERLAY, true).apply()
            overlaySwitch.isChecked = false
            showToast("برای نمایش روی Minecraft، اجازهٔ نمایش روی برنامه‌های دیگر را فعال کن.")
            try {
                startActivity(Intent(Settings.ACTION_MANAGE_OVERLAY_PERMISSION, Uri.parse("package:$packageName")))
            } catch (_: Throwable) {
                showToast("تنظیم نمایش روی برنامه‌های دیگر را از تنظیمات اندروید باز کن.")
            }
            return
        }
        overlayWanted = checked
        preferences.edit().putBoolean(KEY_OVERLAY, checked).apply()
        if (checked) VoiceCallService.showOverlay(this) else VoiceCallService.hideOverlay(this)
    }

    @Deprecated("Deprecated in Android API, retained for API 26 compatibility")
    override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<out String>, grantResults: IntArray) {
        super.onRequestPermissionsResult(requestCode, permissions, grantResults)
        if (requestCode == REQUEST_MIC) {
            if (grantResults.isNotEmpty() && grantResults[0] == PackageManager.PERMISSION_GRANTED) {
                connectNow()
            } else {
                showToast("برای گفت‌وگوی صوتی اجازهٔ میکروفن لازم است.")
            }
        }
    }

    private fun updateConnectionStatus() {
        if (!::statusTitle.isInitialized) return
        when (state) {
            ConnectionState.OFFLINE -> {
                statusTitle.text = "آمادهٔ اتصال"
                statusSubtitle.text = if (gatewayUrl.isBlank()) "درگاه صوتی هنوز تنظیم نشده است." else "کد یک‌بارمصرف را از داخل بازی بگیر."
                statusDot.background = shape(PURPLE, 50, Color.TRANSPARENT)
            }
            ConnectionState.CONNECTING -> {
                statusTitle.text = "در حال اتصال…"
                statusSubtitle.text = "در حال بررسی کد و برقراری صدای امن"
                statusDot.background = shape(ORANGE, 50, Color.TRANSPARENT)
            }
            ConnectionState.CONNECTED -> {
                val name = VoiceSession.currentGrant?.displayName.orEmpty()
                statusTitle.text = if (name.isBlank()) "به گفت‌وگو وصل شدی" else "سلام، $name!"
                statusSubtitle.text = "برای صحبت دکمه را نگه‌دار؛ صدای دیگران خودکار پخش می‌شود."
                statusDot.background = shape(GREEN, 50, Color.TRANSPARENT)
            }
        }
        val pulse = android.animation.ObjectAnimator.ofFloat(statusDot, View.SCALE_X, 1f, 1.18f).apply {
            duration = 850
            repeatMode = android.animation.ValueAnimator.REVERSE
            repeatCount = android.animation.ValueAnimator.INFINITE
        }
        val pulseY = android.animation.ObjectAnimator.ofFloat(statusDot, View.SCALE_Y, 1f, 1.18f).apply {
            duration = 850
            repeatMode = android.animation.ValueAnimator.REVERSE
            repeatCount = android.animation.ValueAnimator.INFINITE
        }
        statusPulse = android.animation.AnimatorSet().apply {
            playTogether(pulse, pulseY)
            start()
        }
    }

    private fun infoCard(title: String, body: String): View = cardColumn().apply {
        setPadding(dp(17), dp(15), dp(17), dp(15))
        addView(text(title, 14, INK, true))
        addView(text(body, 11, MUTED, false).apply { setPadding(0, dp(6), 0, 0) })
    }

    private fun cardColumn(): LinearLayout = LinearLayout(this).apply {
        orientation = LinearLayout.VERTICAL
        layoutDirection = View.LAYOUT_DIRECTION_RTL
        background = shape(SURFACE, 25, BORDER)
        elevation = dp(2).toFloat()
    }

    private fun primaryButton(label: String, action: (() -> Unit)? = null): TextView = TextView(this).apply {
        text = label
        textSize = 16f
        typeface = Typeface.DEFAULT_BOLD
        setTextColor(Color.WHITE)
        gravity = Gravity.CENTER
        background = gradient(PURPLE, 0xff6d4bd8.toInt(), 18)
        elevation = dp(3).toFloat()
        if (action != null) setOnClickListener { animateTap(this); action() }
        else setOnTouchListener { view, event ->
            when (event.actionMasked) {
                MotionEvent.ACTION_DOWN -> view.animate().scaleX(0.98f).scaleY(0.98f).setDuration(80).start()
                MotionEvent.ACTION_UP, MotionEvent.ACTION_CANCEL -> view.animate().scaleX(1f).scaleY(1f).setDuration(110).start()
            }
            false
        }
    }

    private fun secondaryButton(label: String, color: Int, action: () -> Unit): TextView = TextView(this).apply {
        text = label
        textSize = 12f
        typeface = Typeface.DEFAULT_BOLD
        gravity = Gravity.CENTER
        setTextColor(INK)
        background = shape(color, 16, BORDER)
        setOnClickListener { animateTap(this); action() }
    }

    private fun animateTap(view: View) {
        view.performHapticFeedback(HapticFeedbackConstants.KEYBOARD_TAP)
        view.animate().scaleX(0.975f).scaleY(0.975f).setDuration(75).withEndAction {
            view.animate().scaleX(1f).scaleY(1f).setDuration(115).start()
        }.start()
    }

    private fun text(value: String, size: Int, color: Int, bold: Boolean): TextView = TextView(this).apply {
        text = value
        textSize = size.toFloat()
        setTextColor(color)
        if (bold) typeface = Typeface.DEFAULT_BOLD
        gravity = Gravity.CENTER_VERTICAL
        layoutDirection = View.LAYOUT_DIRECTION_RTL
    }

    private fun shape(color: Int, radius: Int, stroke: Int): GradientDrawable = GradientDrawable().apply {
        setColor(color)
        cornerRadius = dp(radius).toFloat()
        if (stroke != Color.TRANSPARENT) setStroke(dp(1), stroke)
    }

    private fun gradient(start: Int, end: Int, radius: Int): GradientDrawable = GradientDrawable(
        GradientDrawable.Orientation.TL_BR,
        intArrayOf(start, end),
    ).apply { cornerRadius = dp(radius).toFloat() }

    private fun sectionGap(value: Int): View = View(this).apply { layoutParams = LinearLayout.LayoutParams(1, dp(value)) }
    private fun matchWrap() = LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT)
    private fun wrapWrap() = LinearLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT)
    private fun dp(value: Int): Int = (value * resources.displayMetrics.density).toInt()
    private fun showToast(message: String) = Toast.makeText(this, message, Toast.LENGTH_LONG).show()

    private enum class Page { VOICE, NEARBY, SETTINGS }
    private enum class ConnectionState { OFFLINE, CONNECTING, CONNECTED }

    companion object {
        private const val REQUEST_MIC = 813
        private const val KEY_GATEWAY = "gateway_url"
        private const val KEY_OVERLAY = "overlay_enabled"

        private const val BG = 0xfff7f4fc.toInt()
        private const val SURFACE = 0xffffffff.toInt()
        private const val INK = 0xff28163f.toInt()
        private const val MUTED = 0xff7c6c91.toInt()
        private const val PURPLE = 0xff7c3aed.toInt()
        private const val DEEP = 0xff4c1d95.toInt()
        private const val LAVENDER_WASH = 0xfff0eafd.toInt()
        private const val BORDER = 0xffe6ddf4.toInt()
        private const val GREEN = 0xff16885c.toInt()
        private const val GREEN_WASH = 0xffe5f6ee.toInt()
        private const val RED_WASH = 0xffffeeee.toInt()
        private const val ORANGE = 0xffd58a16.toInt()
    }
}
