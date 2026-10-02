package ir.arvangaming.app;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.content.Intent;
import android.content.SharedPreferences;
import android.graphics.Color;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.provider.CalendarContract;
import android.text.Editable;
import android.text.InputType;
import android.text.TextWatcher;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.view.WindowManager;
import android.view.animation.DecelerateInterpolator;
import android.widget.EditText;
import android.widget.FrameLayout;
import android.widget.LinearLayout;
import android.widget.RadioButton;
import android.widget.RadioGroup;
import android.widget.ScrollView;
import android.widget.TextView;
import android.widget.Toast;

import org.json.JSONArray;
import org.json.JSONException;
import org.json.JSONObject;

import java.io.IOException;
import java.text.SimpleDateFormat;
import java.util.ArrayList;
import java.util.Date;
import java.util.List;
import java.util.Locale;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;

/** Arvan Gaming's Persian companion app for the Minecraft Bedrock/PocketMine community. */
public final class MainActivity extends Activity {
    private static final int BG = Color.rgb(248, 246, 253);
    private static final int PANEL = Color.rgb(255, 255, 255);
    private static final int PANEL_HI = Color.rgb(244, 239, 252);
    private static final int BORDER = Color.rgb(233, 226, 245);
    private static final int TEXT = Color.rgb(44, 32, 70);
    private static final int MUTED = Color.rgb(125, 112, 145);
    private static final int PURPLE = Color.rgb(124, 81, 210);
    private static final int PURPLE_DARK = Color.rgb(91, 56, 167);
    private static final int PURPLE_LIGHT = Color.rgb(239, 232, 252);
    private static final int BLUE = Color.rgb(92, 116, 218);
    private static final int GREEN = Color.rgb(42, 151, 99);
    private static final int ORANGE = Color.rgb(206, 134, 39);
    private static final int GOLD = Color.rgb(181, 126, 31);
    private static final int PINK = Color.rgb(202, 84, 142);

    private final Handler mainHandler = new Handler(Looper.getMainLooper());
    private final ExecutorService ioExecutor = Executors.newFixedThreadPool(2);
    private SharedPreferences preferences;
    private LinearLayout root;
    private FrameLayout pageHost;
    private String currentPage = "home";
    private JSONObject publicFeed = new JSONObject();
    private JSONObject linkedProfile;
    private String linkToken;
    private String linkSessionId;
    private String linkCode;
    private String linkCommand;
    private long linkExpiresAtMillis;
    private boolean feedLoading;
    private boolean pingLoading;
    private boolean linkPollLoading;
    private long lastStatusCheckedAt;
    private String pingState = "unknown";
    private String pingDetail = "آمار زنده هنوز از سرور دریافت نشده است.";
    private BedrockPingClient.Result lastPing;
    private TextView homeCountdown;
    private long homeCountdownAt;
    private TextView[] navItems;
    private TextView[] navIcons;
    private LinearLayout[] navContainers;
    private int homeSlideIndex;
    private FrameLayout homeCarousel;
    private FrameLayout phoneMockup;
    private LinearLayout phonePreviewScreen;
    private TextView carouselTitle;
    private TextView carouselSubtitle;
    private View[] carouselDots;
    private LinearLayout searchResults;
    private String activeSearchQuery = "";

    private final Runnable homeCarouselTicker = new Runnable() {
        @Override
        public void run() {
            if (homeCarousel == null || !"home".equals(currentPage) || isFinishing()) return;
            showHomeSlide(homeSlideIndex + 1, true);
        }
    };

    private final Runnable countdownRunnable = new Runnable() {
        @Override
        public void run() {
            if (homeCountdown == null || homeCountdownAt <= 0 || isFinishing()) return;
            homeCountdown.setText(formatCountdown(homeCountdownAt));
            mainHandler.postDelayed(this, 1000L);
        }
    };

    private final Runnable linkPollRunnable = new Runnable() {
        @Override
        public void run() {
            pollLinkStatus();
        }
    };

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        getWindow().setStatusBarColor(PURPLE_DARK);
        getWindow().setNavigationBarColor(android.os.Build.VERSION.SDK_INT >= 26 ? Color.WHITE : PURPLE_DARK);
        int systemUi = 0;
        if (android.os.Build.VERSION.SDK_INT >= 26) {
            systemUi |= View.SYSTEM_UI_FLAG_LIGHT_NAVIGATION_BAR;
        }
        getWindow().getDecorView().setSystemUiVisibility(systemUi);
        getWindow().setSoftInputMode(WindowManager.LayoutParams.SOFT_INPUT_ADJUST_RESIZE);
        preferences = getSharedPreferences("arvan_gaming_app", MODE_PRIVATE);
        loadCachedFeed();
        buildRoot();
        renderPage();
        refreshPublicFeed(false);
        refreshServerStatus(false);
    }

    @Override
    protected void onResume() {
        super.onResume();
        if ("home".equals(currentPage)) refreshServerStatus(false);
        startCountdownTicker();
        startHomeCarouselTicker();
    }

    @Override
    protected void onPause() {
        super.onPause();
        mainHandler.removeCallbacks(countdownRunnable);
        mainHandler.removeCallbacks(homeCarouselTicker);
    }

    @Override
    protected void onDestroy() {
        mainHandler.removeCallbacks(countdownRunnable);
        mainHandler.removeCallbacks(homeCarouselTicker);
        mainHandler.removeCallbacks(linkPollRunnable);
        ioExecutor.shutdownNow();
        super.onDestroy();
    }

    @Override
    public void onBackPressed() {
        if (!"home".equals(currentPage)) {
            navigate("home");
        } else {
            super.onBackPressed();
        }
    }

    private void buildRoot() {
        root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        root.setBackgroundColor(BG);

        pageHost = new FrameLayout(this);
        root.addView(pageHost, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, 0, 1f));
        LinearLayout.LayoutParams navigationParams = new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, dp(74));
        navigationParams.setMargins(dp(12), dp(3), dp(12), dp(8));
        root.addView(buildBottomNavigation(), navigationParams);
        setContentView(root);
    }

    private View buildBottomNavigation() {
        LinearLayout bar = new LinearLayout(this);
        bar.setOrientation(LinearLayout.HORIZONTAL);
        bar.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        bar.setGravity(Gravity.CENTER_VERTICAL);
        bar.setPadding(dp(7), dp(6), dp(7), dp(6));
        bar.setBackground(rounded(PANEL, 25, BORDER));
        bar.setElevation(dp(8));
        navItems = new TextView[4];
        navIcons = new TextView[4];
        navContainers = new LinearLayout[4];
        String[] titles = new String[]{"خانه", "رویدادها", "پروفایل", "بیشتر"};
        String[] icons = new String[]{"⌂", "✦", "◉", "⋯"};
        final String[] pages = new String[]{"home", "events", "profile", "more"};
        for (int i = 0; i < titles.length; i++) {
            final String page = pages[i];
            LinearLayout item = new LinearLayout(this);
            item.setOrientation(LinearLayout.VERTICAL);
            item.setGravity(Gravity.CENTER);
            item.setPadding(dp(4), dp(4), dp(4), dp(4));
            item.setBackground(rounded(Color.TRANSPARENT, 19, 0));
            TextView icon = centered(icons[i], 19, MUTED, true);
            TextView title = centered(titles[i], 10, MUTED, false);
            item.addView(icon);
            item.addView(title);
            navItems[i] = title;
            navIcons[i] = icon;
            navContainers[i] = item;
            LinearLayout.LayoutParams itemParams = new LinearLayout.LayoutParams(
                    0, ViewGroup.LayoutParams.MATCH_PARENT, 1f);
            itemParams.setMargins(dp(3), 0, dp(3), 0);
            bar.addView(item, itemParams);
            setAnimatedClick(item, new Runnable() {
                @Override public void run() { navigate(page); }
            });
        }
        updateNavigationColors();
        return bar;
    }

    private void navigate(String page) {
        if (page == null) return;
        currentPage = page;
        renderPage();
        if ("guide".equals(page)) unlockBadge("guide");
        if ("home".equals(page)) refreshServerStatus(false);
    }

    private void renderPage() {
        mainHandler.removeCallbacks(countdownRunnable);
        mainHandler.removeCallbacks(homeCarouselTicker);
        homeCountdown = null;
        homeCountdownAt = 0L;
        homeCarousel = null;
        phoneMockup = null;
        phonePreviewScreen = null;
        searchResults = null;
        pageHost.removeAllViews();

        LinearLayout page;
        if ("guide".equals(currentPage)) page = buildGuidePage();
        else if ("search".equals(currentPage)) page = buildSearchPage();
        else if ("events".equals(currentPage)) page = buildEventsPage();
        else if ("profile".equals(currentPage)) page = buildProfilePage();
        else if ("news".equals(currentPage)) page = buildNewsPage();
        else if ("community".equals(currentPage)) page = buildCommunityPage();
        else if ("more".equals(currentPage)) page = buildMorePage();
        else page = buildHomePage();

        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        scroll.setVerticalScrollBarEnabled(false);
        scroll.setClipToPadding(false);
        scroll.setBackgroundColor(BG);
        scroll.setPadding(0, 0, 0, dp(12));
        scroll.addView(page, new ScrollView.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        pageHost.addView(scroll, new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
        scroll.setAlpha(0f);
        scroll.setTranslationY(dp(10));
        scroll.animate().alpha(1f).translationY(0f).setDuration(320)
                .setInterpolator(new DecelerateInterpolator()).start();
        animatePageChildren(page);
        updateNavigationColors();
        startCountdownTicker();
        startHomeCarouselTicker();
    }

    private void animatePageChildren(LinearLayout page) {
        for (int i = 0; i < page.getChildCount(); i++) {
            View child = page.getChildAt(i);
            child.setAlpha(0f);
            child.setTranslationY(dp(12));
            child.animate().alpha(1f).translationY(0f)
                    .setStartDelay(Math.min(300, i * 42L))
                    .setDuration(360)
                    .setInterpolator(new DecelerateInterpolator())
                    .start();
        }
    }

    private LinearLayout newPage() {
        LinearLayout column = new LinearLayout(this);
        column.setOrientation(LinearLayout.VERTICAL);
        column.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        column.setPadding(dp(18), dp(13), dp(18), dp(20));
        column.setBackgroundColor(BG);
        return column;
    }

    private void addHeader(LinearLayout column) {
        LinearLayout row = horizontal();
        row.setGravity(Gravity.CENTER_VERTICAL);
        row.setPadding(dp(18), dp(9), dp(18), dp(9));
        row.setBackgroundColor(PURPLE_DARK);

        TextView mark = centered("آ", 23, Color.WHITE, true);
        mark.setBackground(rounded(0x28ffffff, 15, 0));
        LinearLayout.LayoutParams markParams = new LinearLayout.LayoutParams(dp(43), dp(43));
        row.addView(mark, markParams);
        LinearLayout titles = new LinearLayout(this);
        titles.setOrientation(LinearLayout.VERTICAL);
        titles.setPadding(0, 0, dp(10), 0);
        titles.addView(label("آروان گیمینگ", 16, Color.WHITE, true));
        titles.addView(label("سرور Minecraft Bedrock", 9, 0xffe7def6, false));
        row.addView(titles, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));

        TextView search = centered("⌕", 25, Color.WHITE, true);
        search.setContentDescription("جستجو در برنامه");
        search.setBackground(rounded(0x24ffffff, 14, 0x44ffffff));
        LinearLayout.LayoutParams searchParams = new LinearLayout.LayoutParams(dp(40), dp(40));
        searchParams.leftMargin = dp(7);
        row.addView(search, searchParams);
        setAnimatedClick(search, new Runnable() {
            @Override public void run() {
                if (!"search".equals(currentPage)) navigate("search");
            }
        });

        TextView refresh = centered("↻", 21, Color.WHITE, true);
        refresh.setContentDescription("به‌روزرسانی اطلاعات");
        refresh.setBackground(rounded(0x24ffffff, 14, 0x44ffffff));
        row.addView(refresh, new LinearLayout.LayoutParams(dp(40), dp(40)));
        setAnimatedClick(refresh, new Runnable() {
            @Override public void run() {
                refresh.animate().rotationBy(360f).setDuration(450).start();
                refreshPublicFeed(true);
                refreshServerStatus(true);
            }
        });

        LinearLayout.LayoutParams headerParams = new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, dp(62));
        headerParams.setMargins(-dp(18), -dp(13), -dp(18), dp(15));
        column.addView(row, headerParams);
    }

    private LinearLayout buildHomePage() {
        LinearLayout column = newPage();
        addHeader(column);
        column.addView(buildHomeCarousel(), matchWrap());
        space(column, 16);
        column.addView(buildServerCard(), matchWrap());
        space(column, 14);
        column.addView(buildNextEventCard(), matchWrap());
        space(column, 14);
        column.addView(buildNewsPreview(), matchWrap());
        space(column, 14);
        column.addView(label("دسترسی سریع", 16, TEXT, true), matchWrap());
        space(column, 9);
        LinearLayout quick = horizontal();
        quick.addView(actionCard("راهنمای اتصال", "قدم‌به‌قدم وارد سرور شو", "➜", PURPLE,
                new Runnable() { @Override public void run() { navigate("guide"); } }),
                new LinearLayout.LayoutParams(0, dp(104), 1f));
        View gap = new View(this);
        quick.addView(gap, new LinearLayout.LayoutParams(dp(10), 1));
        quick.addView(actionCard("اخبار و نظرسنجی", "تازه‌های جامعه", "✦", BLUE,
                new Runnable() { @Override public void run() { navigate("news"); } }),
                new LinearLayout.LayoutParams(0, dp(104), 1f));
        column.addView(quick, matchWrap());
        space(column, 10);
        TextView privacy = centered("وضعیت و آمار فقط با پاسخ واقعی سرور نمایش داده می‌شود.", 10, MUTED, false);
        column.addView(privacy, matchWrap());
        return column;
    }

    private View buildHomeCarousel() {
        FrameLayout stage = new FrameLayout(this);
        homeCarousel = stage;
        stage.setMinimumHeight(dp(510));
        stage.setBackground(gradient(new int[]{0xfff8f5ff, 0xffeee6fb, 0xffe9def8}, 29, 0xffe9e0f4));
        stage.setClipToOutline(true);

        TextView ornament = centered("✧", 105, 0x247c51d2, true);
        FrameLayout.LayoutParams ornamentParams = new FrameLayout.LayoutParams(dp(130), dp(130),
                Gravity.TOP | Gravity.LEFT);
        ornamentParams.leftMargin = dp(-18);
        ornamentParams.topMargin = dp(58);
        stage.addView(ornament, ornamentParams);
        TextView ornament2 = centered("✦", 58, 0x307c51d2, true);
        FrameLayout.LayoutParams ornament2Params = new FrameLayout.LayoutParams(dp(80), dp(80),
                Gravity.BOTTOM | Gravity.RIGHT);
        ornament2Params.rightMargin = dp(4);
        ornament2Params.bottomMargin = dp(55);
        stage.addView(ornament2, ornament2Params);

        LinearLayout heading = new LinearLayout(this);
        heading.setOrientation(LinearLayout.VERTICAL);
        heading.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        heading.setGravity(Gravity.CENTER);
        carouselTitle = centered("", 25, PURPLE_DARK, true);
        carouselSubtitle = centered("", 12, MUTED, false);
        heading.addView(carouselTitle, matchWrap());
        space(heading, 4);
        heading.addView(carouselSubtitle, matchWrap());
        FrameLayout.LayoutParams headingParams = new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT,
                Gravity.TOP | Gravity.CENTER_HORIZONTAL);
        headingParams.setMargins(dp(14), dp(23), dp(14), 0);
        stage.addView(heading, headingParams);

        phoneMockup = buildPhonePreview();
        FrameLayout.LayoutParams phoneParams = new FrameLayout.LayoutParams(dp(218), dp(332),
                Gravity.BOTTOM | Gravity.CENTER_HORIZONTAL);
        phoneParams.bottomMargin = dp(43);
        stage.addView(phoneMockup, phoneParams);
        setAnimatedClick(phoneMockup, new Runnable() {
            @Override public void run() { openCurrentHomeSlide(); }
        });

        TextView previous = centered("‹", 31, Color.WHITE, true);
        previous.setBackground(rounded(withAlpha(PURPLE_DARK, 0.80f), 30, 0));
        previous.setElevation(dp(4));
        previous.setContentDescription("صفحهٔ قبلی");
        FrameLayout.LayoutParams previousParams = new FrameLayout.LayoutParams(dp(40), dp(40),
                Gravity.CENTER_VERTICAL | Gravity.LEFT);
        previousParams.leftMargin = dp(9);
        stage.addView(previous, previousParams);
        setAnimatedClick(previous, new Runnable() {
            @Override public void run() { showHomeSlide(homeSlideIndex - 1, true); }
        });

        TextView next = centered("›", 31, Color.WHITE, true);
        next.setBackground(rounded(withAlpha(PURPLE_DARK, 0.80f), 30, 0));
        next.setElevation(dp(4));
        next.setContentDescription("صفحهٔ بعدی");
        FrameLayout.LayoutParams nextParams = new FrameLayout.LayoutParams(dp(40), dp(40),
                Gravity.CENTER_VERTICAL | Gravity.RIGHT);
        nextParams.rightMargin = dp(9);
        stage.addView(next, nextParams);
        setAnimatedClick(next, new Runnable() {
            @Override public void run() { showHomeSlide(homeSlideIndex + 1, true); }
        });

        LinearLayout dots = new LinearLayout(this);
        dots.setOrientation(LinearLayout.HORIZONTAL);
        dots.setLayoutDirection(View.LAYOUT_DIRECTION_LTR);
        dots.setGravity(Gravity.CENTER);
        dots.setPadding(dp(8), dp(5), dp(8), dp(5));
        carouselDots = new View[4];
        for (int i = 0; i < carouselDots.length; i++) {
            final int slide = i;
            View dot = new View(this);
            LinearLayout.LayoutParams dotParams = new LinearLayout.LayoutParams(dp(7), dp(7));
            dotParams.setMargins(dp(5), 0, dp(5), 0);
            dots.addView(dot, dotParams);
            carouselDots[i] = dot;
            setAnimatedClick(dot, new Runnable() {
                @Override public void run() { showHomeSlide(slide, true); }
            });
        }
        FrameLayout.LayoutParams dotsParams = new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.WRAP_CONTENT, dp(28), Gravity.BOTTOM | Gravity.CENTER_HORIZONTAL);
        dotsParams.bottomMargin = dp(8);
        stage.addView(dots, dotsParams);
        showHomeSlide(homeSlideIndex, false);
        return stage;
    }

    private FrameLayout buildPhonePreview() {
        FrameLayout device = new FrameLayout(this);
        device.setPadding(dp(7), dp(8), dp(7), dp(8));
        device.setBackground(rounded(PURPLE_DARK, 34, 0));
        device.setElevation(dp(13));
        device.setRotation(-5f);
        device.setContentDescription("پیش‌نمایش تعاملی بخش‌های آروان گیمینگ");

        phonePreviewScreen = new LinearLayout(this);
        phonePreviewScreen.setOrientation(LinearLayout.VERTICAL);
        phonePreviewScreen.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        phonePreviewScreen.setPadding(dp(9), dp(16), dp(9), dp(8));
        phonePreviewScreen.setBackground(rounded(Color.WHITE, 27, 0));
        device.addView(phonePreviewScreen, new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));

        TextView notch = new TextView(this);
        notch.setBackground(rounded(PURPLE_DARK, 10, 0));
        FrameLayout.LayoutParams notchParams = new FrameLayout.LayoutParams(dp(58), dp(9),
                Gravity.TOP | Gravity.CENTER_HORIZONTAL);
        notchParams.topMargin = dp(5);
        device.addView(notch, notchParams);
        renderPhonePreview();
        return device;
    }

    private void renderPhonePreview() {
        if (phonePreviewScreen == null) return;
        phonePreviewScreen.removeAllViews();
        LinearLayout appBar = horizontal();
        appBar.setGravity(Gravity.CENTER_VERTICAL);
        TextView appMark = centered("آ", 12, Color.WHITE, true);
        appMark.setBackground(rounded(PURPLE, 10, 0));
        appBar.addView(appMark, new LinearLayout.LayoutParams(dp(24), dp(24)));
        TextView appName = label("آروان گیمینگ", 10, TEXT, true);
        LinearLayout.LayoutParams appNameParams = new LinearLayout.LayoutParams(
                0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f);
        appNameParams.rightMargin = dp(6);
        appBar.addView(appName, appNameParams);
        appBar.addView(centered("⋯", 17, MUTED, true), new LinearLayout.LayoutParams(dp(22), dp(24)));
        phonePreviewScreen.addView(appBar, matchWrap());
        space(phonePreviewScreen, 6);
        View divider = new View(this);
        divider.setBackgroundColor(BORDER);
        phonePreviewScreen.addView(divider, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, dp(1)));
        space(phonePreviewScreen, 7);

        if (homeSlideIndex == 0) renderStationPreview();
        else if (homeSlideIndex == 1) renderSearchPreview();
        else if (homeSlideIndex == 2) renderNewsPreview();
        else renderPagesPreview();
    }

    private void renderStationPreview() {
        phonePreviewScreen.addView(label("ایستگاه خدمات", 11, TEXT, true), matchWrap());
        space(phonePreviewScreen, 7);
        LinearLayout tiles = horizontal();
        tiles.addView(miniTile("➜", "راهنمای ورود", PURPLE), new LinearLayout.LayoutParams(0, dp(76), 1f));
        View gap = new View(this);
        tiles.addView(gap, new LinearLayout.LayoutParams(dp(7), 1));
        tiles.addView(miniTile("✎", "ثبت گزارش", BLUE), new LinearLayout.LayoutParams(0, dp(76), 1f));
        phonePreviewScreen.addView(tiles, matchWrap());
        space(phonePreviewScreen, 7);
        phonePreviewScreen.addView(miniCard("✦", "پشتیبانی جامعه", "گزارش و پیشنهاد از بخش انجمن", PURPLE), matchWrap());
        space(phonePreviewScreen, 7);
        phonePreviewScreen.addView(miniCard("🎁", "کد هدیه", "پس از فعال‌شدن سرویس رسمی", ORANGE), matchWrap());
    }

    private void renderSearchPreview() {
        phonePreviewScreen.addView(label("جستجو", 12, TEXT, true), matchWrap());
        space(phonePreviewScreen, 6);
        TextView searchField = label("⌕   جستجو کن...", 9, MUTED, false);
        searchField.setPadding(dp(9), dp(8), dp(9), dp(8));
        searchField.setBackground(rounded(PANEL_HI, 13, BORDER));
        phonePreviewScreen.addView(searchField, matchWrap());
        space(phonePreviewScreen, 8);
        phonePreviewScreen.addView(label("پیشنهادهای دسترسی سریع", 9, MUTED, true), matchWrap());
        space(phonePreviewScreen, 5);
        phonePreviewScreen.addView(miniCard("➜", "راهنمای اتصال", "آدرس، پورت و مراحل ورود", PURPLE), matchWrap());
        space(phonePreviewScreen, 5);
        phonePreviewScreen.addView(miniCard("✦", "رویدادها", "برنامه‌های اعلام‌شده", BLUE), matchWrap());
        space(phonePreviewScreen, 5);
        phonePreviewScreen.addView(miniCard("◉", "اخبار رسمی", "اطلاعیه‌های مدیریت", PINK), matchWrap());
    }

    private void renderNewsPreview() {
        JSONArray news = array(publicFeed, "news");
        JSONObject latest = news.optJSONObject(0);
        String title = latest == null ? "هنوز اطلاعیهٔ رسمی ثبت نشده"
                : compactText(safeText(latest.optString("title"), "اطلاعیهٔ آروان"), 42);
        String summary = latest == null ? "خبرهای مدیریت پس از انتشار اینجا نمایش داده می‌شوند."
                : compactText(safeText(latest.optString("summary"), latest.optString("body")), 86);
        phonePreviewScreen.addView(pill("اخبار سرور", PURPLE, PURPLE_LIGHT), wrap());
        space(phonePreviewScreen, 7);
        FrameLayout newsArtwork = new FrameLayout(this);
        newsArtwork.setBackground(gradient(new int[]{0xff7950d2, 0xffaa64df}, 15, 0));
        TextView artwork = centered("✦", 36, 0x66ffffff, true);
        newsArtwork.addView(artwork, new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
        phonePreviewScreen.addView(newsArtwork, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, dp(75)));
        space(phonePreviewScreen, 7);
        phonePreviewScreen.addView(label(title, 10, TEXT, true), matchWrap());
        space(phonePreviewScreen, 4);
        phonePreviewScreen.addView(label(summary, 8, MUTED, false), matchWrap());
        space(phonePreviewScreen, 8);
        phonePreviewScreen.addView(miniCard("◉", "نظرسنجی جامعه", "رأی واقعی پس از اتصال API", BLUE), matchWrap());
    }

    private void renderPagesPreview() {
        TextView banner = centered("همه‌چیز برای جامعهٔ آروان", 10, Color.WHITE, true);
        banner.setPadding(dp(7), dp(12), dp(7), dp(12));
        banner.setBackground(gradient(new int[]{0xff7549d2, 0xffa85bdf}, 14, 0));
        phonePreviewScreen.addView(banner, matchWrap());
        space(phonePreviewScreen, 8);
        LinearLayout firstRow = horizontal();
        firstRow.addView(miniTile("✦", "رویدادها", BLUE), new LinearLayout.LayoutParams(0, dp(70), 1f));
        View gap1 = new View(this);
        firstRow.addView(gap1, new LinearLayout.LayoutParams(dp(6), 1));
        firstRow.addView(miniTile("◉", "پروفایل", PURPLE), new LinearLayout.LayoutParams(0, dp(70), 1f));
        phonePreviewScreen.addView(firstRow, matchWrap());
        space(phonePreviewScreen, 6);
        LinearLayout secondRow = horizontal();
        secondRow.addView(miniTile("➜", "راهنما", ORANGE), new LinearLayout.LayoutParams(0, dp(70), 1f));
        View gap2 = new View(this);
        secondRow.addView(gap2, new LinearLayout.LayoutParams(dp(6), 1));
        secondRow.addView(miniTile("♧", "انجمن", PINK), new LinearLayout.LayoutParams(0, dp(70), 1f));
        phonePreviewScreen.addView(secondRow, matchWrap());
    }

    private LinearLayout miniCard(String icon, String title, String subtitle, int accent) {
        LinearLayout row = horizontal();
        row.setGravity(Gravity.CENTER_VERTICAL);
        row.setPadding(dp(7), dp(7), dp(7), dp(7));
        row.setBackground(rounded(Color.WHITE, 13, BORDER));
        TextView mark = centered(icon, 14, accent, true);
        mark.setBackground(rounded(withAlpha(accent, 0.13f), 11, 0));
        row.addView(mark, new LinearLayout.LayoutParams(dp(31), dp(31)));
        LinearLayout copy = new LinearLayout(this);
        copy.setOrientation(LinearLayout.VERTICAL);
        copy.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        copy.addView(label(title, 9, TEXT, true));
        copy.addView(label(subtitle, 7, MUTED, false));
        LinearLayout.LayoutParams copyParams = new LinearLayout.LayoutParams(
                0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f);
        copyParams.rightMargin = dp(6);
        row.addView(copy, copyParams);
        return row;
    }

    private LinearLayout miniTile(String icon, String title, int accent) {
        LinearLayout tile = new LinearLayout(this);
        tile.setOrientation(LinearLayout.VERTICAL);
        tile.setGravity(Gravity.CENTER);
        tile.setPadding(dp(4), dp(5), dp(4), dp(5));
        tile.setBackground(rounded(Color.WHITE, 13, BORDER));
        TextView mark = centered(icon, 19, accent, true);
        mark.setBackground(rounded(withAlpha(accent, 0.12f), 11, 0));
        tile.addView(mark, new LinearLayout.LayoutParams(dp(34), dp(34)));
        TextView caption = centered(title, 8, TEXT, true);
        LinearLayout.LayoutParams captionParams = wrap();
        captionParams.topMargin = dp(4);
        tile.addView(caption, captionParams);
        return tile;
    }

    private void showHomeSlide(int requestedIndex, boolean animate) {
        homeSlideIndex = (requestedIndex % 4 + 4) % 4;
        String[] titles = new String[]{"ایستگاه", "جستجو", "اخبار", "صفحه‌ها"};
        String[] subtitles = new String[]{
                "ثبت گزارش، کد هدیه و خدمات سرور",
                "مطالب و راهنماهای تازه را پیدا کن!",
                "از خبرهای رسمی سرور مطلع باش!",
                "راهنما، رویداد و پروفایل؛ همه یک‌جا"
        };
        if (carouselTitle != null) carouselTitle.setText(titles[homeSlideIndex]);
        if (carouselSubtitle != null) carouselSubtitle.setText(subtitles[homeSlideIndex]);
        renderPhonePreview();
        if (carouselDots != null) {
            for (int i = 0; i < carouselDots.length; i++) {
                boolean active = i == homeSlideIndex;
                View dot = carouselDots[i];
                LinearLayout.LayoutParams params = (LinearLayout.LayoutParams) dot.getLayoutParams();
                params.width = dp(active ? 22 : 7);
                params.height = dp(7);
                dot.setLayoutParams(params);
                dot.setBackground(rounded(active ? PURPLE : withAlpha(PURPLE, 0.28f), 12, 0));
            }
        }
        if (animate) {
            if (carouselTitle != null) {
                carouselTitle.setAlpha(0f);
                carouselTitle.setTranslationY(dp(8));
                carouselTitle.animate().alpha(1f).translationY(0f).setDuration(260)
                        .setInterpolator(new DecelerateInterpolator()).start();
            }
            if (carouselSubtitle != null) {
                carouselSubtitle.setAlpha(0f);
                carouselSubtitle.animate().alpha(1f).setStartDelay(55).setDuration(250).start();
            }
            if (phoneMockup != null) {
                phoneMockup.animate().cancel();
                phoneMockup.setAlpha(0.55f);
                phoneMockup.setTranslationY(dp(12));
                phoneMockup.setRotation(homeSlideIndex % 2 == 0 ? -4.5f : -6.5f);
                phoneMockup.animate().alpha(1f).translationY(0f).rotation(-5f)
                        .setDuration(350).setInterpolator(new DecelerateInterpolator()).start();
            }
        }
        startHomeCarouselTicker();
    }

    private void startHomeCarouselTicker() {
        mainHandler.removeCallbacks(homeCarouselTicker);
        if (homeCarousel != null && "home".equals(currentPage) && !isFinishing()) {
            mainHandler.postDelayed(homeCarouselTicker, 6500L);
        }
    }

    private void openCurrentHomeSlide() {
        if (homeSlideIndex == 0) navigate("community");
        else if (homeSlideIndex == 1) navigate("search");
        else if (homeSlideIndex == 2) navigate("news");
        else navigate("more");
    }

    private View buildServerCard() {
        LinearLayout card = card();
        LinearLayout heading = horizontal();
        heading.setGravity(Gravity.CENTER_VERTICAL);
        View dot = new View(this);
        dot.setBackground(rounded(pingColor(), 30, 0));
        heading.addView(dot, new LinearLayout.LayoutParams(dp(9), dp(9)));
        TextView title = label("وضعیت سرور", 16, TEXT, true);
        LinearLayout.LayoutParams titleParams = new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f);
        titleParams.rightMargin = dp(8);
        heading.addView(title, titleParams);
        TextView state = pill(statusLabel(), pingColor(), withAlpha(pingColor(), 0.13f));
        heading.addView(state, wrap());
        card.addView(heading, matchWrap());
        space(card, 13);

        String address = displayAddress();
        TextView addressLine = label(hasServerAddress() ? address : "آدرس سرور هنوز توسط مدیریت تنظیم نشده", 13,
                hasServerAddress() ? PURPLE : MUTED, hasServerAddress());
        addressLine.setTextDirection(View.TEXT_DIRECTION_LTR);
        addressLine.setGravity(Gravity.RIGHT);
        card.addView(addressLine, matchWrap());
        space(card, 9);

        LinearLayout stats = horizontal();
        stats.setGravity(Gravity.CENTER_VERTICAL);
        stats.setBackground(rounded(PANEL_HI, 15, 0));
        stats.setPadding(dp(12), dp(12), dp(12), dp(12));
        String playerCount = "—";
        String countCaption = "آمار واقعی پس از پاسخ Bedrock ping";
        if (lastPing != null && lastPing.responded && lastPing.onlinePlayers >= 0) {
            playerCount = String.valueOf(lastPing.onlinePlayers)
                    + (lastPing.maximumPlayers >= 0 ? " / " + lastPing.maximumPlayers : "");
            countCaption = "بازیکن آنلاینِ گزارش‌شده توسط سرور";
        } else if (lastPing != null && lastPing.responded) {
            countCaption = "سرور پاسخ داد؛ شمار بازیکنان در پاسخ نبود";
        } else if ("checking".equals(pingState)) {
            countCaption = "در حال دریافت پاسخ واقعی از سرور…";
        } else if (!hasServerAddress()) {
            countCaption = "آمار ساختگی نمایش داده نمی‌شود";
        } else if (lastStatusCheckedAt > 0) {
            countCaption = "پاسخی دریافت نشد؛ وضعیت نامشخص است";
        }
        TextView number = centered(playerCount, 23, PURPLE_DARK, true);
        stats.addView(number, new LinearLayout.LayoutParams(dp(92), ViewGroup.LayoutParams.WRAP_CONTENT));
        LinearLayout countText = new LinearLayout(this);
        countText.setOrientation(LinearLayout.VERTICAL);
        countText.setPadding(dp(8), 0, 0, 0);
        countText.addView(label("پلیر آنلاین", 12, TEXT, true));
        countText.addView(label(countCaption, 10, MUTED, false));
        stats.addView(countText, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        card.addView(stats, matchWrap());

        if (lastPing != null && lastPing.responded && !lastPing.serverVersion.isEmpty()) {
            space(card, 8);
            card.addView(label("نسخهٔ اعلام‌شدهٔ سرور: " + lastPing.serverVersion, 11, MUTED, false), matchWrap());
        }
        if (lastPing == null && hasServerAddress() && lastStatusCheckedAt == 0) {
            space(card, 7);
            card.addView(label(pingDetail, 10, MUTED, false), matchWrap());
        }

        space(card, 13);
        LinearLayout actions = horizontal();
        TextView copy = button("کپی آدرس", hasServerAddress() ? PANEL_HI : 0xfff2eef8,
                hasServerAddress() ? TEXT : MUTED, true);
        actions.addView(copy, new LinearLayout.LayoutParams(0, dp(43), 1f));
        View gap = new View(this);
        actions.addView(gap, new LinearLayout.LayoutParams(dp(9), 1));
        TextView refresh = button(pingLoading ? "در حال بررسی…" : "بررسی دوباره", PURPLE, Color.WHITE, true);
        actions.addView(refresh, new LinearLayout.LayoutParams(0, dp(43), 1f));
        card.addView(actions, matchWrap());
        setAnimatedClick(copy, new Runnable() {
            @Override public void run() { copyServerAddress(); }
        });
        setAnimatedClick(refresh, new Runnable() {
            @Override public void run() { refreshServerStatus(true); }
        });
        return card;
    }

    private View buildNextEventCard() {
        JSONObject event = nextEvent();
        LinearLayout card = card();
        LinearLayout top = horizontal();
        top.setGravity(Gravity.CENTER_VERTICAL);
        TextView icon = centered("✦", 19, ORANGE, true);
        top.addView(icon, new LinearLayout.LayoutParams(dp(36), dp(36)));
        LinearLayout titleBox = new LinearLayout(this);
        titleBox.setOrientation(LinearLayout.VERTICAL);
        titleBox.setPadding(dp(9), 0, 0, 0);
        titleBox.addView(label("خبر مهم بعدی", 11, ORANGE, true));
        String eventTitle = event == null ? "رویداد رسمی هنوز اعلام نشده" : safeText(event.optString("title"), "رویداد آروان گیمینگ");
        titleBox.addView(label(eventTitle, 15, TEXT, true));
        top.addView(titleBox, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        card.addView(top, matchWrap());
        space(card, 8);

        if (event == null) {
            card.addView(label("به‌محض دریافت برنامهٔ رسمی از سرور، زمان و شمارش معکوس اینجا نمایش داده می‌شود.",
                    11, MUTED, false), matchWrap());
        } else {
            String description = safeText(event.optString("description"), "جزئیات رویداد هنوز ثبت نشده است.");
            card.addView(label(description, 11, MUTED, false), matchWrap());
            long startsAt = event.optLong("startsAt", 0L);
            if (startsAt > 0L) {
                space(card, 11);
                homeCountdown = label(formatCountdown(startsAt), 17, PURPLE, true);
                homeCountdownAt = startsAt;
                card.addView(homeCountdown, matchWrap());
                space(card, 5);
                card.addView(label(formatDate(startsAt), 10, MUTED, false), matchWrap());
            }
            space(card, 12);
            LinearLayout row = horizontal();
            String eventId = event.optString("id", "");
            TextView reminder = button("یادآور", PANEL_HI, TEXT, true);
            row.addView(reminder, new LinearLayout.LayoutParams(0, dp(40), 1f));
            setAnimatedClick(reminder, new Runnable() {
                @Override public void run() { addCalendarReminder(event); }
            });
            String registrationUrl = safeText(event.optString("registrationUrl"), "");
            if (!registrationUrl.isEmpty()) {
                View gap = new View(this);
                row.addView(gap, new LinearLayout.LayoutParams(dp(8), 1));
                TextView register = button("ثبت‌نام", PURPLE, Color.WHITE, true);
                row.addView(register, new LinearLayout.LayoutParams(0, dp(40), 1f));
                setAnimatedClick(register, new Runnable() {
                    @Override public void run() { openHttpsUrl(registrationUrl); }
                });
            }
            card.addView(row, matchWrap());
        }
        return card;
    }

    private View buildNewsPreview() {
        LinearLayout card = card();
        LinearLayout row = horizontal();
        row.setGravity(Gravity.CENTER_VERTICAL);
        row.addView(label("خبرهای تازه", 15, TEXT, true),
                new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        TextView more = label("همهٔ خبرها  ←", 10, PURPLE, true);
        row.addView(more, wrap());
        setAnimatedClick(more, new Runnable() {
            @Override public void run() { navigate("news"); }
        });
        card.addView(row, matchWrap());
        space(card, 10);
        JSONArray news = array(publicFeed, "news");
        if (news.length() == 0) {
            card.addView(label("هنوز اطلاعیهٔ واقعی از API یا مدیریت سرور دریافت نشده است.", 11, MUTED, false), matchWrap());
        } else {
            JSONObject latest = news.optJSONObject(0);
            if (latest != null) {
                card.addView(pill(latest.optBoolean("important", false) ? "اطلاعیهٔ مهم" : "اطلاعیهٔ سرور",
                        latest.optBoolean("important", false) ? ORANGE : PURPLE,
                        latest.optBoolean("important", false) ? 0xfffff2df : 0xffeee8fb), wrap());
                space(card, 7);
                card.addView(label(safeText(latest.optString("title"), ""), 14, TEXT, true), matchWrap());
                space(card, 4);
                card.addView(label(safeText(latest.optString("summary"), latest.optString("body")), 11, MUTED, false), matchWrap());
            }
        }
        return card;
    }

    private LinearLayout buildSearchPage() {
        LinearLayout column = newPage();
        addHeader(column);
        pageTitle(column, "جستجو", "راهنما، خبر و برنامه‌های رسمی را پیدا کن.", PURPLE);
        space(column, 14);

        LinearLayout searchBar = horizontal();
        searchBar.setGravity(Gravity.CENTER_VERTICAL);
        EditText input = new EditText(this);
        input.setSingleLine(true);
        input.setTextSize(14);
        input.setTextColor(TEXT);
        input.setHintTextColor(MUTED);
        input.setHint("جستجو کن...");
        input.setInputType(InputType.TYPE_CLASS_TEXT);
        input.setTextDirection(View.TEXT_DIRECTION_FIRST_STRONG_RTL);
        input.setGravity(Gravity.RIGHT | Gravity.CENTER_VERTICAL);
        input.setPadding(dp(14), dp(8), dp(14), dp(8));
        input.setBackground(rounded(Color.WHITE, 16, BORDER));
        searchBar.addView(input, new LinearLayout.LayoutParams(0, dp(52), 1f));
        View searchGap = new View(this);
        searchBar.addView(searchGap, new LinearLayout.LayoutParams(dp(8), 1));
        TextView searchButton = button("⌕", PURPLE, Color.WHITE, true);
        searchBar.addView(searchButton, new LinearLayout.LayoutParams(dp(52), dp(52)));
        setAnimatedClick(searchButton, new Runnable() {
            @Override public void run() {
                input.requestFocus();
                android.view.inputmethod.InputMethodManager keyboard =
                        (android.view.inputmethod.InputMethodManager) getSystemService(Context.INPUT_METHOD_SERVICE);
                if (keyboard != null) keyboard.showSoftInput(input,
                        android.view.inputmethod.InputMethodManager.SHOW_IMPLICIT);
            }
        });
        column.addView(searchBar, matchWrap());
        space(column, 17);
        TextView resultsTitle = label("پیشنهادهای دسترسی سریع", 15, TEXT, true);
        column.addView(resultsTitle, matchWrap());
        space(column, 8);
        searchResults = new LinearLayout(this);
        searchResults.setOrientation(LinearLayout.VERTICAL);
        searchResults.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        column.addView(searchResults, matchWrap());
        input.setText(activeSearchQuery);
        renderSearchResults(activeSearchQuery);

        input.addTextChangedListener(new TextWatcher() {
            @Override public void beforeTextChanged(CharSequence s, int start, int count, int after) { }
            @Override public void onTextChanged(CharSequence s, int start, int before, int count) {
                activeSearchQuery = s == null ? "" : s.toString();
                renderSearchResults(activeSearchQuery);
            }
            @Override public void afterTextChanged(Editable s) { }
        });
        return column;
    }

    private void renderSearchResults(String query) {
        if (searchResults == null) return;
        searchResults.removeAllViews();
        String normalizedQuery = normalizeSearch(query);
        List<SearchItem> items = searchableItems();
        int shown = 0;
        for (final SearchItem item : items) {
            String haystack = normalizeSearch(item.title + " " + item.subtitle);
            boolean visible = normalizedQuery.isEmpty() ? item.featured : haystack.contains(normalizedQuery);
            if (!visible) continue;
            LinearLayout.LayoutParams params = matchWrap();
            params.bottomMargin = dp(9);
            searchResults.addView(actionCard(item.title, item.subtitle, item.icon, item.accent,
                    new Runnable() { @Override public void run() { navigate(item.destination); } }), params);
            shown++;
        }
        if (shown == 0) {
            searchResults.addView(emptyCard("چیزی پیدا نشد",
                    "عبارت دیگری را جستجو کن؛ محتوای سرور فقط از فید رسمی می‌آید.", "⌕"), matchWrap());
        }
    }

    private List<SearchItem> searchableItems() {
        ArrayList<SearchItem> items = new ArrayList<>();
        items.add(new SearchItem("آموزش اتصال", "آدرس، پورت و مراحل ورود Bedrock", "➜", PURPLE, "guide", true));
        items.add(new SearchItem("رویدادهای آروان", "برنامه‌های رسمی و زمان‌بندی‌شده", "✦", BLUE, "events", true));
        items.add(new SearchItem("اخبار رسمی", "اطلاعیه‌های سرور و نظرسنجی‌ها", "◉", PINK, "news", true));
        items.add(new SearchItem("پروفایل پلیر", "نام نمایشی و اتصال امن حساب", "♙", PURPLE, "profile", true));
        items.add(new SearchItem("انجمن و پشتیبانی", "کانال‌ها و ثبت پیشنهاد", "♧", ORANGE, "community", true));

        JSONArray news = array(publicFeed, "news");
        for (int i = 0; i < news.length() && i < 20; i++) {
            JSONObject item = news.optJSONObject(i);
            if (item == null) continue;
            String title = safeText(item.optString("title"), "اطلاعیهٔ آروان گیمینگ");
            String body = safeText(item.optString("summary"), item.optString("body"));
            items.add(new SearchItem(title, body, "◉", PINK, "news", false));
        }
        JSONArray events = array(publicFeed, "events");
        for (int i = 0; i < events.length() && i < 20; i++) {
            JSONObject item = events.optJSONObject(i);
            if (item == null) continue;
            String title = safeText(item.optString("title"), "رویداد آروان گیمینگ");
            String body = safeText(item.optString("description"), "رویداد رسمی سرور");
            items.add(new SearchItem(title, body, "✦", BLUE, "events", false));
        }
        JSONArray rules = array(publicFeed, "rules");
        for (int i = 0; i < rules.length() && i < 20; i++) {
            String rule = rules.optString(i, "");
            if (!rule.trim().isEmpty()) {
                items.add(new SearchItem("قانون سرور", rule, "✓", GREEN, "guide", false));
            }
        }
        return items;
    }

    private String normalizeSearch(String value) {
        if (value == null) return "";
        return value.trim().toLowerCase(Locale.ROOT).replace('ي', 'ی').replace('ك', 'ک');
    }

    private LinearLayout buildGuidePage() {
        LinearLayout column = newPage();
        addHeader(column);
        pageTitle(column, "راهنمای ورود", "در چند قدم ساده وارد دنیای Bedrock آروان شو.", PURPLE);
        space(column, 14);
        LinearLayout address = card();
        address.addView(label("آدرس و پورت سرور", 15, TEXT, true), matchWrap());
        space(address, 8);
        address.addView(label(hasServerAddress() ? displayAddress()
                : "آدرس و پورت رسمی هنوز در برنامه تنظیم نشده‌اند.", 13, hasServerAddress() ? PURPLE : MUTED, true), matchWrap());
        space(address, 11);
        TextView copy = button("کپی آدرس سرور", hasServerAddress() ? PURPLE : 0xfff2eef8,
                hasServerAddress() ? Color.WHITE : MUTED, true);
        address.addView(copy, matchWrap());
        setAnimatedClick(copy, new Runnable() {
            @Override public void run() { copyServerAddress(); }
        });
        column.addView(address, matchWrap());
        space(column, 13);

        LinearLayout steps = card();
        steps.addView(label("اضافه‌کردن سرور در Bedrock موبایل", 15, TEXT, true), matchWrap());
        space(steps, 13);
        String[] instructions = new String[]{
                "Minecraft Bedrock را در گوشی باز کن.",
                "از صفحهٔ اصلی وارد Play / بازی شو و زبانهٔ Servers / سرورها را انتخاب کن.",
                "گزینهٔ Add Server / افزودن سرور را بزن.",
                "نام دلخواه «آروان گیمینگ» را بنویس؛ آدرس و پورت را دقیقاً از کارت بالای همین صفحه وارد کن.",
                "Save / ذخیره را بزن و سپس Join / پیوستن را انتخاب کن."
        };
        for (int i = 0; i < instructions.length; i++) {
            steps.addView(numberedStep(i + 1, instructions[i]), matchWrap());
            if (i < instructions.length - 1) space(steps, 10);
        }
        column.addView(steps, matchWrap());
        space(column, 13);

        LinearLayout versions = card();
        versions.addView(label("نسخه‌های سازگار", 15, TEXT, true), matchWrap());
        space(versions, 7);
        String versionText = supportedVersionsText();
        versions.addView(label(versionText, 11, supportedVersions().length > 0 ? GREEN : MUTED, false), matchWrap());
        column.addView(versions, matchWrap());
        space(column, 13);

        LinearLayout rules = card();
        rules.addView(label("قوانین سرور", 15, TEXT, true), matchWrap());
        space(rules, 8);
        JSONArray ruleArray = array(publicFeed, "rules");
        if (ruleArray.length() == 0) {
            rules.addView(label("قوانین رسمی هنوز از سوی مدیریت منتشر نشده‌اند؛ برای جلوگیری از اطلاعات نادرست، قانونی حدس نزده‌ایم.",
                    11, MUTED, false), matchWrap());
        } else {
            for (int i = 0; i < ruleArray.length(); i++) {
                String rule = ruleArray.optString(i, "");
                if (!rule.trim().isEmpty()) {
                    rules.addView(numberedStep(i + 1, rule), matchWrap());
                    space(rules, 7);
                }
            }
        }
        column.addView(rules, matchWrap());
        space(column, 13);
        column.addView(buildSupportCard(), matchWrap());
        return column;
    }

    private LinearLayout buildEventsPage() {
        LinearLayout column = newPage();
        addHeader(column);
        pageTitle(column, "رویدادهای آروان", "مسابقه، ساخت‌وساز گروهی و برنامه‌های فصلی.", ORANGE);
        space(column, 14);
        JSONArray events = array(publicFeed, "events");
        if (events.length() == 0) {
            column.addView(emptyCard("تقویم رویدادها هنوز خالی است",
                    "زمان مسابقه‌ها، نگه‌داری سرور و برنامه‌های فصلی فقط پس از دریافت اطلاعیهٔ واقعی نمایش داده می‌شود.", "✦"), matchWrap());
        }
        for (int i = 0; i < events.length(); i++) {
            JSONObject event = events.optJSONObject(i);
            if (event == null) continue;
            column.addView(buildEventCard(event), matchWrap());
            space(column, 12);
        }
        return column;
    }

    private View buildEventCard(final JSONObject event) {
        LinearLayout card = card();
        card.addView(pill(event.optBoolean("important", false) ? "رویداد مهم" : "رویداد", ORANGE, 0xfffff2df), wrap());
        space(card, 9);
        card.addView(label(safeText(event.optString("title"), "رویداد آروان گیمینگ"), 17, TEXT, true), matchWrap());
        long start = event.optLong("startsAt", 0L);
        if (start > 0) {
            space(card, 5);
            card.addView(label(formatDate(start), 11, PURPLE, true), matchWrap());
            space(card, 4);
            card.addView(label(formatCountdown(start), 13, ORANGE, true), matchWrap());
        }
        space(card, 8);
        card.addView(label(safeText(event.optString("description"), "جزئیات این رویداد هنوز درج نشده است."), 11, MUTED, false), matchWrap());
        String winners = safeText(event.optString("winners"), "");
        String result = safeText(event.optString("result"), "");
        if (!winners.isEmpty() || !result.isEmpty()) {
            space(card, 10);
            card.addView(label("نتیجه و برندگان", 12, GREEN, true), matchWrap());
            if (!result.isEmpty()) card.addView(label(result, 11, TEXT, false), matchWrap());
            if (!winners.isEmpty()) card.addView(label("برندگان: " + winners, 11, GOLD, true), matchWrap());
        }
        space(card, 12);
        LinearLayout buttons = horizontal();
        long eventAt = start;
        TextView reminder = button("یادآور در تقویم", PANEL_HI, TEXT, true);
        buttons.addView(reminder, new LinearLayout.LayoutParams(0, dp(42), 1f));
        setAnimatedClick(reminder, new Runnable() {
            @Override public void run() { addCalendarReminder(event); }
        });
        String registrationUrl = safeText(event.optString("registrationUrl"), "");
        if (!registrationUrl.isEmpty()) {
            View gap = new View(this);
            buttons.addView(gap, new LinearLayout.LayoutParams(dp(8), 1));
            TextView register = button("ثبت‌نام", PURPLE_DARK, Color.WHITE, true);
            buttons.addView(register, new LinearLayout.LayoutParams(0, dp(42), 1f));
            setAnimatedClick(register, new Runnable() {
                @Override public void run() { openHttpsUrl(registrationUrl); }
            });
        }
        card.addView(buttons, matchWrap());
        return card;
    }

    private LinearLayout buildProfilePage() {
        LinearLayout column = newPage();
        addHeader(column);
        pageTitle(column, "پروفایل پلیر", "آمار سروری فقط پس از اتصال امن نمایش داده می‌شود.", PURPLE);
        space(column, 14);

        final String localName = preferences.getString("player_name", "").trim();
        LinearLayout local = card();
        LinearLayout identity = horizontal();
        identity.setGravity(Gravity.CENTER_VERTICAL);
        TextView avatar = centered(localName.isEmpty() ? "آ" : "✦", 24, Color.WHITE, true);
        avatar.setBackground(gradient(new int[]{0xff7549d2, 0xffb15fe3}, 19, 0));
        identity.addView(avatar, new LinearLayout.LayoutParams(dp(58), dp(58)));
        LinearLayout identityText = new LinearLayout(this);
        identityText.setOrientation(LinearLayout.VERTICAL);
        identityText.addView(label("پروفایل ماجراجو", 11, MUTED, true));
        identityText.addView(label(localName.isEmpty() ? "نامت را برای کارتت انتخاب کن" : localName,
                17, TEXT, true));
        LinearLayout.LayoutParams identityTextParams = new LinearLayout.LayoutParams(
                0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f);
        identityTextParams.rightMargin = dp(12);
        identity.addView(identityText, identityTextParams);
        local.addView(identity, matchWrap());
        space(local, 12);
        TextView editName = button(localName.isEmpty() ? "ثبت نام نمایشی" : "ویرایش نام", PANEL_HI, TEXT, true);
        local.addView(editName, matchWrap());
        setAnimatedClick(editName, new Runnable() {
            @Override public void run() { editLocalPlayerName(); }
        });
        column.addView(local, matchWrap());
        space(column, 12);

        LinearLayout onlineProfile = card();
        onlineProfile.addView(label("آمار داخل سرور", 15, TEXT, true), matchWrap());
        space(onlineProfile, 7);
        if (linkedProfile != null && linkToken != null) {
            String name = safeText(linkedProfile.optString("playerName"), "بازیکن متصل‌شده");
            onlineProfile.addView(label("حساب متصل: " + name, 13, GREEN, true), matchWrap());
            addProfileValue(onlineProfile, "رتبه", linkedProfile, "rank");
            addProfileValue(onlineProfile, "زمان بازی", linkedProfile, "playtime");
            addProfileValue(onlineProfile, "آمار", linkedProfile, "statsSummary");
        } else {
            onlineProfile.addView(label("رتبه، زمان بازی و آمار تا زمانی که API امن سرور پاسخ واقعی ندهد نمایش داده نمی‌شوند.",
                    11, MUTED, false), matchWrap());
            space(onlineProfile, 10);
            TextView connect = button(ArvanGamingConfig.hasSecureApi() ? "اتصال با کد یک‌بارمصرف" : "اتصال امن پس از راه‌اندازی API سرور",
                    ArvanGamingConfig.hasSecureApi() ? PURPLE : 0xfff2eef8,
                    ArvanGamingConfig.hasSecureApi() ? Color.WHITE : MUTED, true);
            onlineProfile.addView(connect, matchWrap());
            setAnimatedClick(connect, new Runnable() {
                @Override public void run() { startAccountLink(); }
            });
        }
        space(onlineProfile, 8);
        onlineProfile.addView(label("هیچ‌وقت رمز Microsoft یا Xbox را در اپ وارد نکن؛ اتصال فقط با کد یک‌بارمصرفی است که سرور صادر می‌کند.",
                10, ORANGE, false), matchWrap());
        column.addView(onlineProfile, matchWrap());
        space(column, 12);

        LinearLayout badges = card();
        badges.addView(label("نشان‌های داخل اپ", 15, TEXT, true), matchWrap());
        space(badges, 9);
        LinearLayout badgeRow = horizontal();
        badgeRow.addView(badge("راهنماخوان", hasBadge("guide"), "📘"), new LinearLayout.LayoutParams(0, dp(74), 1f));
        View gap1 = new View(this);
        badgeRow.addView(gap1, new LinearLayout.LayoutParams(dp(7), 1));
        badgeRow.addView(badge("رویدادیار", hasBadge("reminder"), "⏰"), new LinearLayout.LayoutParams(0, dp(74), 1f));
        View gap2 = new View(this);
        badgeRow.addView(gap2, new LinearLayout.LayoutParams(dp(7), 1));
        badgeRow.addView(badge("هم‌رسان", hasBadge("share"), "✦"), new LinearLayout.LayoutParams(0, dp(74), 1f));
        badges.addView(badgeRow, matchWrap());
        space(badges, 7);
        badges.addView(label("این نشان‌ها فقط برای استفاده از خود اپ هستند و رتبه یا دستاورد داخل سرور محسوب نمی‌شوند.",
                10, MUTED, false), matchWrap());
        column.addView(badges, matchWrap());
        space(column, 12);
        TextView share = button("اشتراک‌گذاری کارت پلیر", 0xffeee8fb, TEXT, true);
        column.addView(share, matchWrap());
        setAnimatedClick(share, new Runnable() {
            @Override public void run() { shareProfileCard(); }
        });
        return column;
    }

    private LinearLayout buildNewsPage() {
        LinearLayout column = newPage();
        addHeader(column);
        pageTitle(column, "اخبار و نظرسنجی", "اطلاعیه‌های رسمی و نظرهای جامعهٔ آروان.", PINK);
        space(column, 14);
        JSONArray news = array(publicFeed, "news");
        if (news.length() == 0) {
            column.addView(emptyCard("فعلاً خبری دریافت نشده",
                    "این بخش خبر ساختگی نشان نمی‌دهد؛ اطلاعیهٔ آپدیت یا قطعی پس از اتصال فید رسمی اینجا می‌آید.", "✦"), matchWrap());
            space(column, 12);
        }
        for (int i = 0; i < news.length(); i++) {
            JSONObject item = news.optJSONObject(i);
            if (item == null) continue;
            column.addView(buildNewsCard(item), matchWrap());
            space(column, 10);
        }

        column.addView(label("نظرسنجی جامعه", 17, TEXT, true), matchWrap());
        space(column, 8);
        JSONArray polls = array(publicFeed, "polls");
        if (polls.length() == 0) {
            column.addView(emptyCard("نظرسنجی فعالی نیست",
                    "برای جلوگیری از رأی‌گیری ساختگی، رأی‌ها فقط وقتی ثبت می‌شوند که API رسمی سرور در دسترس باشد.", "◉"), matchWrap());
        }
        for (int i = 0; i < polls.length(); i++) {
            JSONObject poll = polls.optJSONObject(i);
            if (poll == null) continue;
            column.addView(buildPollCard(poll), matchWrap());
            space(column, 10);
        }
        space(column, 12);
        TextView report = button("گزارش مشکل یا پیشنهاد", PURPLE_DARK, Color.WHITE, true);
        column.addView(report, matchWrap());
        setAnimatedClick(report, new Runnable() {
            @Override public void run() { showReportDialog(); }
        });
        return column;
    }

    private View buildNewsCard(JSONObject item) {
        LinearLayout card = card();
        boolean important = item.optBoolean("important", false);
        card.addView(pill(important ? "خبر مهم" : "اطلاعیه", important ? ORANGE : PURPLE,
                important ? 0xfffff2df : 0xfff0eaff), wrap());
        space(card, 8);
        card.addView(label(safeText(item.optString("title"), "اطلاعیهٔ آروان گیمینگ"), 15, TEXT, true), matchWrap());
        String summary = safeText(item.optString("summary"), item.optString("body"));
        if (!summary.isEmpty()) {
            space(card, 5);
            card.addView(label(summary, 11, MUTED, false), matchWrap());
        }
        long published = item.optLong("publishedAt", 0L);
        if (published > 0) {
            space(card, 7);
            card.addView(label(formatDate(published), 10, MUTED, false), matchWrap());
        }
        return card;
    }

    private View buildPollCard(final JSONObject poll) {
        LinearLayout card = card();
        card.addView(label(safeText(poll.optString("question"), "نظرسنجی"), 14, TEXT, true), matchWrap());
        space(card, 8);
        JSONArray options = array(poll, "options");
        for (int i = 0; i < options.length(); i++) {
            final JSONObject optionObject = options.optJSONObject(i);
            final String optionId;
            final String optionTitle;
            if (optionObject != null) {
                optionId = safeText(optionObject.optString("id"), String.valueOf(i));
                optionTitle = safeText(optionObject.optString("title"), "گزینهٔ " + (i + 1));
            } else {
                optionId = String.valueOf(i);
                optionTitle = options.optString(i, "گزینهٔ " + (i + 1));
            }
            TextView vote = button("◉  " + optionTitle, PANEL_HI, TEXT, false);
            LinearLayout.LayoutParams params = matchWrap();
            params.bottomMargin = dp(6);
            card.addView(vote, params);
            setAnimatedClick(vote, new Runnable() {
                @Override public void run() { submitPollVote(poll.optString("id", ""), optionId); }
            });
        }
        long endsAt = poll.optLong("endsAt", 0L);
        if (endsAt > 0) {
            space(card, 4);
            card.addView(label("پایان رأی‌گیری: " + formatDate(endsAt), 10, MUTED, false), matchWrap());
        }
        return card;
    }

    private LinearLayout buildCommunityPage() {
        LinearLayout column = newPage();
        addHeader(column);
        pageTitle(column, "انجمن و دعوت دوستان", "ماجراجویی را با دوست‌هایت به اشتراک بگذار.", BLUE);
        space(column, 14);
        LinearLayout channels = card();
        channels.addView(label("کانال‌های رسمی", 15, TEXT, true), matchWrap());
        space(channels, 7);
        JSONObject links = object(publicFeed, "links");
        addExternalLink(channels, "وب‌سایت و پشتیبانی", safeText(links.optString("support"), ArvanGamingConfig.SUPPORT_URL));
        addExternalLink(channels, "تلگرام", safeText(links.optString("telegram"), ArvanGamingConfig.TELEGRAM_URL));
        addExternalLink(channels, "دیسکورد", safeText(links.optString("discord"), ArvanGamingConfig.DISCORD_URL));
        addExternalLink(channels, "اینستاگرام", safeText(links.optString("instagram"), ArvanGamingConfig.INSTAGRAM_URL));
        if (!hasAnyOfficialLink(links)) {
            space(channels, 2);
            channels.addView(label("لینک کانال رسمی هنوز از طرف مدیریت ثبت نشده است.", 11, MUTED, false), matchWrap());
        }
        column.addView(channels, matchWrap());
        space(column, 12);

        LinearLayout invite = card();
        invite.addView(label("دعوت از دوست‌ها", 15, TEXT, true), matchWrap());
        space(invite, 7);
        String inviteCode = safeText(object(publicFeed, "server").optString("inviteCode"), "");
        if (inviteCode.isEmpty()) {
            invite.addView(label("کد دعوت هنوز توسط سرور ساخته نشده؛ می‌توانی لینک و آدرس تأییدشدهٔ سرور را به اشتراک بگذاری.",
                    11, MUTED, false), matchWrap());
        } else {
            invite.addView(label("کد دعوت: " + inviteCode, 18, PURPLE, true), matchWrap());
            space(invite, 5);
        }
        space(invite, 11);
        TextView share = button("اشتراک‌گذاری آروان گیمینگ", PURPLE, Color.WHITE, true);
        invite.addView(share, matchWrap());
        setAnimatedClick(share, new Runnable() {
            @Override public void run() { shareInvite(); }
        });
        column.addView(invite, matchWrap());
        space(column, 12);
        TextView report = button("ارسال گزارش یا پیشنهاد", PURPLE_DARK, Color.WHITE, true);
        column.addView(report, matchWrap());
        setAnimatedClick(report, new Runnable() {
            @Override public void run() { showReportDialog(); }
        });
        return column;
    }

    private LinearLayout buildMorePage() {
        LinearLayout column = newPage();
        addHeader(column);
        pageTitle(column, "بیشتر", "همهٔ ابزارهای همراه سرور در دسترس توست.", PURPLE);
        space(column, 14);
        column.addView(actionCard("راهنمای ورود", "آدرس، پورت، نسخه‌ها و مراحل اتصال", "➜", PURPLE,
                new Runnable() { @Override public void run() { navigate("guide"); } }), matchWrap());
        space(column, 10);
        column.addView(actionCard("اخبار و نظرسنجی", "به‌روزرسانی‌ها، رویدادها و رأی‌گیری", "✦", PINK,
                new Runnable() { @Override public void run() { navigate("news"); } }), matchWrap());
        space(column, 10);
        column.addView(actionCard("انجمن و دعوت", "کانال‌های رسمی و اشتراک‌گذاری", "♧", BLUE,
                new Runnable() { @Override public void run() { navigate("community"); } }), matchWrap());
        space(column, 10);
        column.addView(buildSupportCard(), matchWrap());
        space(column, 14);
        column.addView(label("حریم خصوصی", 13, TEXT, true), matchWrap());
        space(column, 5);
        column.addView(label("اپ هیچ‌وقت رمز Microsoft یا Xbox نمی‌پرسد. آمار پلیر فقط پس از تأیید کد یک‌بارمصرف و اتصال امن HTTPS نمایش داده می‌شود.",
                10, MUTED, false), matchWrap());
        return column;
    }

    private View buildSupportCard() {
        LinearLayout card = card();
        card.addView(label("پشتیبانی آروان گیمینگ", 15, TEXT, true), matchWrap());
        space(card, 6);
        JSONObject links = object(publicFeed, "links");
        String support = safeText(links.optString("support"), ArvanGamingConfig.SUPPORT_URL);
        if (!support.isEmpty()) {
            card.addView(label("برای کمک، صفحهٔ رسمی پشتیبانی را باز کن.", 11, MUTED, false), matchWrap());
            space(card, 9);
            TextView button = button("ارتباط با پشتیبانی", PURPLE, Color.WHITE, true);
            card.addView(button, matchWrap());
            setAnimatedClick(button, new Runnable() {
                @Override public void run() { openHttpsUrl(support); }
            });
        } else {
            card.addView(label("راه ارتباطی هنوز توسط مدیریت در برنامه ثبت نشده است.", 11, MUTED, false), matchWrap());
        }
        return card;
    }

    private View actionCard(String title, String subtitle, String icon, int accent, Runnable action) {
        LinearLayout box = card();
        LinearLayout row = horizontal();
        row.setGravity(Gravity.CENTER_VERTICAL);
        TextView symbol = centered(icon, 18, accent, true);
        symbol.setBackground(rounded(withAlpha(accent, 0.12f), 12, 0));
        row.addView(symbol, new LinearLayout.LayoutParams(dp(38), dp(38)));
        LinearLayout copy = new LinearLayout(this);
        copy.setOrientation(LinearLayout.VERTICAL);
        copy.setPadding(dp(10), 0, 0, 0);
        copy.addView(label(title, 13, TEXT, true));
        copy.addView(label(subtitle, 10, MUTED, false));
        row.addView(copy, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        row.addView(centered("←", 17, accent, true), wrap());
        box.addView(row, matchWrap());
        setAnimatedClick(box, action);
        return box;
    }

    private View emptyCard(String title, String body, String icon) {
        LinearLayout box = card();
        box.setGravity(Gravity.CENTER);
        TextView symbol = centered(icon, 23, PURPLE, true);
        symbol.setBackground(rounded(PANEL_HI, 18, 0));
        box.addView(symbol, new LinearLayout.LayoutParams(dp(50), dp(50)));
        space(box, 9);
        box.addView(centered(title, 14, TEXT, true), matchWrap());
        space(box, 6);
        box.addView(centered(body, 11, MUTED, false), matchWrap());
        return box;
    }

    private View numberedStep(int number, String description) {
        LinearLayout row = horizontal();
        row.setGravity(Gravity.TOP);
        TextView badge = centered(String.valueOf(number), 12, BG, true);
        badge.setBackground(rounded(PURPLE, 20, 0));
        row.addView(badge, new LinearLayout.LayoutParams(dp(28), dp(28)));
        TextView text = label(description, 11, TEXT, false);
        text.setPadding(dp(9), dp(4), 0, 0);
        row.addView(text, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        return row;
    }

    private View badge(String title, boolean unlocked, String icon) {
        LinearLayout box = new LinearLayout(this);
        box.setOrientation(LinearLayout.VERTICAL);
        box.setGravity(Gravity.CENTER);
        box.setPadding(dp(4), dp(6), dp(4), dp(6));
        box.setBackground(rounded(unlocked ? 0xffe8f5ed : PANEL_HI, 14, unlocked ? 0xffa8dec2 : BORDER));
        box.addView(centered(icon, 17, unlocked ? GREEN : MUTED, true));
        box.addView(centered(title, 9, unlocked ? TEXT : MUTED, unlocked));
        return box;
    }

    private void addProfileValue(LinearLayout box, String label, JSONObject profile, String key) {
        if (profile == null || !profile.has(key)) return;
        space(box, 5);
        box.addView(label(label + ": " + safeText(profile.optString(key), "—"), 12, PURPLE, true), matchWrap());
    }

    private void addExternalLink(LinearLayout parent, String label, String url) {
        if (url == null || url.trim().isEmpty()) return;
        TextView link = button(label + "  ↗", PANEL_HI, TEXT, false);
        LinearLayout.LayoutParams params = matchWrap();
        params.topMargin = dp(6);
        parent.addView(link, params);
        setAnimatedClick(link, new Runnable() {
            @Override public void run() { openHttpsUrl(url); }
        });
    }

    private void editLocalPlayerName() {
        final EditText input = new EditText(this);
        input.setSingleLine(true);
        input.setHint("نام نمایشی در کارت اپ");
        input.setText(preferences.getString("player_name", ""));
        input.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_FLAG_CAP_WORDS);
        input.setPadding(dp(14), dp(12), dp(14), dp(12));
        new AlertDialog.Builder(this)
                .setTitle("نام نمایشی")
                .setMessage("این نام فقط داخل همین اپ ذخیره می‌شود و از سرور تأیید نشده است.")
                .setView(input)
                .setNegativeButton("بی‌خیال", null)
                .setPositiveButton("ذخیره", (dialog, which) -> {
                    String name = input.getText().toString().trim();
                    if (name.length() > 24) name = name.substring(0, 24);
                    preferences.edit().putString("player_name", name).apply();
                    renderPage();
                }).show();
    }

    private void startAccountLink() {
        if (!ArvanGamingConfig.hasSecureApi()) {
            new AlertDialog.Builder(this)
                    .setTitle("اتصال امن هنوز فعال نشده")
                    .setMessage("برای دریافت کد یک‌بارمصرف، سرور PocketMine باید API امن HTTPS و فرمان اتصال داخل بازی را راه‌اندازی کند. این اپ رمز Microsoft یا Xbox نمی‌خواهد.")
                    .setPositiveButton("متوجه شدم", null)
                    .show();
            return;
        }
        final EditText nameInput = new EditText(this);
        nameInput.setSingleLine(true);
        nameInput.setHint("نام پلیر در بازی");
        nameInput.setInputType(InputType.TYPE_CLASS_TEXT);
        nameInput.setText(preferences.getString("player_name", ""));
        new AlertDialog.Builder(this)
                .setTitle("اتصال با کد یک‌بارمصرف")
                .setMessage("نام داخل بازی را وارد کن. رمز حساب Microsoft/Xbox هرگز لازم نیست.")
                .setView(nameInput)
                .setNegativeButton("لغو", null)
                .setPositiveButton("گرفتن کد", (dialog, which) -> {
                    String playerName = nameInput.getText().toString().trim();
                    if (playerName.isEmpty()) {
                        toast("اول نام داخل بازی را وارد کن.");
                        return;
                    }
                    preferences.edit().putString("player_name", playerName).apply();
                    requestLinkCode(playerName);
                }).show();
    }

    private void requestLinkCode(final String playerName) {
        if (!ArvanGamingConfig.hasSecureApi()) return;
        toast("در حال درخواست کد امن از سرور…");
        ioExecutor.execute(new Runnable() {
            @Override public void run() {
                try {
                    JSONObject body = new JSONObject();
                    body.put("playerName", playerName);
                    final JSONObject response = ArvanApi.post(ArvanApi.endpoint("/v1/link/start"), body, null);
                    final String code = safeText(response.optString("code"), "");
                    final String session = safeText(response.optString("sessionId"), "");
                    final long ttl = Math.max(30L, Math.min(900L, response.optLong("expiresInSeconds", 300L)));
                    final String command = safeText(response.optString("command"), code.isEmpty() ? "" : "/link " + code);
                    if (code.isEmpty() || session.isEmpty()) throw new IOException("API پاسخ کامل کد اتصال را نداد.");
                    mainHandler.post(new Runnable() {
                        @Override public void run() {
                            linkCode = code;
                            linkSessionId = session;
                            linkCommand = command;
                            linkExpiresAtMillis = System.currentTimeMillis() + ttl * 1000L;
                            showLinkCodeDialog(ttl);
                            mainHandler.removeCallbacks(linkPollRunnable);
                            mainHandler.postDelayed(linkPollRunnable, 5000L);
                        }
                    });
                } catch (final Exception error) {
                    mainHandler.post(new Runnable() {
                        @Override public void run() { toast("کد اتصال دریافت نشد: " + safeText(error.getMessage(), "خطای ارتباط")); }
                    });
                }
            }
        });
    }

    private void showLinkCodeDialog(long ttlSeconds) {
        LinearLayout content = new LinearLayout(this);
        content.setOrientation(LinearLayout.VERTICAL);
        content.setPadding(dp(20), dp(8), dp(20), dp(8));
        content.addView(centered(linkCode, 28, PURPLE, true), matchWrap());
        space(content, 8);
        content.addView(centered("در بازی فرمان زیر را اجرا کن:", 12, MUTED, false), matchWrap());
        space(content, 4);
        content.addView(centered(linkCommand, 15, ORANGE, true), matchWrap());
        space(content, 8);
        content.addView(centered("کد یک‌بارمصرف است و حدود " + (ttlSeconds / 60) + " دقیقه اعتبار دارد.",
                10, MUTED, false), matchWrap());
        new AlertDialog.Builder(this)
                .setTitle("اتصال حساب — بدون رمز")
                .setView(content)
                .setNegativeButton("بستن", null)
                .setNeutralButton("کپی کد", (dialog, which) -> copyText(linkCode, "کد اتصال کپی شد"))
                .show();
    }

    private void pollLinkStatus() {
        if (linkSessionId == null || linkToken != null || linkPollLoading) return;
        if (System.currentTimeMillis() >= linkExpiresAtMillis) {
            linkSessionId = null;
            toast("اعتبار کد اتصال تمام شد؛ دوباره کد بگیر.");
            return;
        }
        linkPollLoading = true;
        final String session = linkSessionId;
        ioExecutor.execute(new Runnable() {
            @Override public void run() {
                try {
                    String url = ArvanApi.endpoint("/v1/link/status?sessionId=" + Uri.encode(session));
                    final JSONObject response = ArvanApi.get(url, null);
                    final boolean linked = response.optBoolean("linked", false);
                    if (linked) {
                        final String token = safeText(response.optString("accessToken"), "");
                        final JSONObject inlineProfile = response.optJSONObject("profile");
                        if (token.isEmpty()) throw new IOException("اتصال تأیید شد اما access token دریافت نشد.");
                        final JSONObject profile = inlineProfile != null ? inlineProfile
                                : ArvanApi.get(ArvanApi.endpoint("/v1/profile"), token);
                        mainHandler.post(new Runnable() {
                            @Override public void run() {
                                linkToken = token; // In-memory only; never persist this bearer token.
                                linkedProfile = profile;
                                linkSessionId = null;
                                mainHandler.removeCallbacks(linkPollRunnable);
                                toast("حساب با موفقیت و از راه امن متصل شد.");
                                unlockBadge("linked");
                                if ("profile".equals(currentPage)) renderPage();
                            }
                        });
                        return;
                    }
                } catch (final Exception ignored) {
                    // Keep polling until the one-time code expires; never log access tokens or codes.
                } finally {
                    mainHandler.post(new Runnable() {
                        @Override public void run() {
                            linkPollLoading = false;
                            if (linkSessionId != null && linkToken == null
                                    && System.currentTimeMillis() < linkExpiresAtMillis) {
                                mainHandler.postDelayed(linkPollRunnable, 5000L);
                            }
                        }
                    });
                }
            }
        });
    }

    private void submitPollVote(final String pollId, final String optionId) {
        if (!ArvanGamingConfig.hasSecureApi()) {
            toast("برای ثبت رأی واقعی، API نظرسنجی سرور لازم است.");
            return;
        }
        if (pollId == null || pollId.trim().isEmpty()) {
            toast("شناسهٔ نظرسنجی معتبر نیست.");
            return;
        }
        ioExecutor.execute(new Runnable() {
            @Override public void run() {
                try {
                    JSONObject body = new JSONObject();
                    body.put("optionId", optionId);
                    ArvanApi.post(ArvanApi.endpoint("/v1/polls/" + Uri.encode(pollId) + "/vote"), body, null);
                    mainHandler.post(new Runnable() {
                        @Override public void run() {
                            toast("رأی واقعی به سرور ارسال شد.");
                            refreshPublicFeed(false);
                        }
                    });
                } catch (final Exception error) {
                    mainHandler.post(new Runnable() {
                        @Override public void run() { toast("رأی ثبت نشد: " + safeText(error.getMessage(), "خطای API")); }
                    });
                }
            }
        });
    }

    private void showReportDialog() {
        final EditText input = new EditText(this);
        input.setHint("شرح کوتاه مشکل یا پیشنهاد");
        input.setGravity(Gravity.TOP | Gravity.RIGHT);
        input.setMinLines(3);
        input.setMaxLines(6);
        input.setInputType(InputType.TYPE_CLASS_TEXT | InputType.TYPE_TEXT_FLAG_MULTI_LINE | InputType.TYPE_TEXT_FLAG_CAP_SENTENCES);
        input.setPadding(dp(13), dp(12), dp(13), dp(12));
        new AlertDialog.Builder(this)
                .setTitle("گزارش مشکل یا پیشنهاد")
                .setMessage(ArvanGamingConfig.hasSecureApi()
                        ? "گزارش از مسیر HTTPS برای API ارسال می‌شود."
                        : "API ارسال مستقیم تنظیم نشده؛ متن برای ارسال با یکی از برنامه‌های گوشی آماده می‌شود.")
                .setView(input)
                .setNegativeButton("لغو", null)
                .setPositiveButton("ادامه", (dialog, which) -> {
                    String message = input.getText().toString().trim();
                    if (message.isEmpty()) {
                        toast("متن گزارش خالی است.");
                    } else if (ArvanGamingConfig.hasSecureApi()) {
                        sendReportToApi(message);
                    } else {
                        shareText("گزارش برای آروان گیمینگ:\n" + message, "ارسال گزارش با…");
                    }
                }).show();
    }

    private void sendReportToApi(final String message) {
        ioExecutor.execute(new Runnable() {
            @Override public void run() {
                try {
                    JSONObject body = new JSONObject();
                    body.put("message", message);
                    body.put("category", "player-report");
                    ArvanApi.post(ArvanApi.endpoint("/v1/reports"), body, null);
                    mainHandler.post(new Runnable() {
                        @Override public void run() { toast("گزارش به سرور ارسال شد. ممنون!"); }
                    });
                } catch (final Exception error) {
                    mainHandler.post(new Runnable() {
                        @Override public void run() { toast("گزارش ارسال نشد: " + safeText(error.getMessage(), "خطای API")); }
                    });
                }
            }
        });
    }

    private void refreshPublicFeed(final boolean showToast) {
        if (!ArvanGamingConfig.hasSecureApi() || feedLoading) {
            if (showToast && !ArvanGamingConfig.hasSecureApi()) toast("فید رسمی HTTPS هنوز تنظیم نشده است.");
            return;
        }
        feedLoading = true;
        ioExecutor.execute(new Runnable() {
            @Override public void run() {
                try {
                    final JSONObject response = ArvanApi.get(ArvanApi.endpoint("/v1/app/public"), null);
                    preferences.edit().putString("cached_public_feed", response.toString()).apply();
                    mainHandler.post(new Runnable() {
                        @Override public void run() {
                            publicFeed = response;
                            feedLoading = false;
                            renderPage();
                            refreshServerStatus(false);
                            if (showToast) toast("اطلاعات رسمی به‌روز شد.");
                        }
                    });
                } catch (final Exception error) {
                    mainHandler.post(new Runnable() {
                        @Override public void run() {
                            feedLoading = false;
                            if (showToast) toast("اطلاعات سرور دریافت نشد: " + safeText(error.getMessage(), "خطای ارتباط"));
                        }
                    });
                }
            }
        });
    }

    private void loadCachedFeed() {
        if (!ArvanGamingConfig.hasSecureApi()) return;
        String raw = preferences.getString("cached_public_feed", "");
        if (raw.isEmpty()) return;
        try {
            publicFeed = new JSONObject(raw);
        } catch (JSONException ignored) {
            publicFeed = new JSONObject();
        }
    }

    private void refreshServerStatus(final boolean showToast) {
        if (pingLoading) return;
        final String host = serverHost();
        final int port = serverPort();
        if (host.isEmpty() || port <= 0) {
            pingState = "unconfigured";
            pingDetail = "آدرس عمومی Bedrock هنوز از مدیریت دریافت نشده است.";
            if (showToast) toast("برای بررسی واقعی، آدرس و پورت سرور باید تنظیم شود.");
            if ("home".equals(currentPage)) renderPage();
            return;
        }
        pingLoading = true;
        pingState = "checking";
        pingDetail = "در انتظار پاسخ واقعی Bedrock…";
        if ("home".equals(currentPage)) renderPage();
        ioExecutor.execute(new Runnable() {
            @Override public void run() {
                BedrockPingClient.Result response = null;
                String detail;
                try {
                    response = BedrockPingClient.ping(host, port, 1800);
                    detail = response.onlinePlayers >= 0
                            ? "دادهٔ زنده از Bedrock دریافت شد."
                            : "پاسخ واقعی دریافت شد؛ شمار پلیر در پاسخ نبود.";
                } catch (Exception error) {
                    detail = "پاسخی دریافت نشد؛ وضعیت را نمی‌توان با اطمینان آفلاین اعلام کرد.";
                }
                final BedrockPingClient.Result finalResponse = response;
                final String finalDetail = detail;
                mainHandler.post(new Runnable() {
                    @Override public void run() {
                        pingLoading = false;
                        lastStatusCheckedAt = System.currentTimeMillis();
                        lastPing = finalResponse;
                        pingDetail = finalDetail;
                        pingState = finalResponse != null && finalResponse.responded ? "online" : "unknown";
                        if ("home".equals(currentPage)) renderPage();
                        if (showToast) toast(finalResponse != null && finalResponse.responded
                                ? "پاسخ واقعی سرور دریافت شد." : "پاسخی دریافت نشد؛ وضعیت نامشخص است.");
                    }
                });
            }
        });
    }

    private void addCalendarReminder(JSONObject event) {
        long begin = event.optLong("startsAt", 0L);
        if (begin <= System.currentTimeMillis()) {
            toast("زمان آیندهٔ معتبری برای این رویداد ثبت نشده است.");
            return;
        }
        try {
            Intent intent = new Intent(Intent.ACTION_INSERT);
            intent.setData(CalendarContract.Events.CONTENT_URI);
            intent.putExtra(CalendarContract.Events.TITLE, safeText(event.optString("title"), "رویداد آروان گیمینگ"));
            intent.putExtra(CalendarContract.Events.DESCRIPTION, safeText(event.optString("description"), ""));
            intent.putExtra(CalendarContract.EXTRA_EVENT_BEGIN_TIME, begin);
            intent.putExtra(CalendarContract.EXTRA_EVENT_END_TIME, begin + 90L * 60L * 1000L);
            startActivity(intent);
            unlockBadge("reminder");
        } catch (Exception error) {
            toast("برنامهٔ تقویم در دسترس نیست.");
        }
    }

    private void editLocalPlayerNameAndShare() {
        editLocalPlayerName();
    }

    private void shareProfileCard() {
        String name = preferences.getString("player_name", "بازیکن آروان");
        StringBuilder message = new StringBuilder("🎮 کارت پلیر آروان گیمینگ\n");
        message.append("نام نمایشی: ").append(name.isEmpty() ? "ماجراجو" : name);
        if (linkedProfile != null && linkToken != null) {
            String rank = safeText(linkedProfile.optString("rank"), "");
            String playtime = safeText(linkedProfile.optString("playtime"), "");
            if (!rank.isEmpty()) message.append("\nرتبهٔ تأییدشده: ").append(rank);
            if (!playtime.isEmpty()) message.append("\nزمان بازی: ").append(playtime);
        } else {
            message.append("\nآمار سروری هنوز به حساب متصل نشده است.");
        }
        message.append("\nساخته‌شده در اپ آروان گیمینگ");
        shareText(message.toString(), "اشتراک‌گذاری کارت پلیر");
        unlockBadge("share");
    }

    private void shareInvite() {
        StringBuilder message = new StringBuilder("🌌 بیا در سرور Minecraft Bedrock آروان گیمینگ بازی کنیم!");
        if (hasServerAddress()) message.append("\nآدرس: ").append(displayAddress());
        String code = safeText(object(publicFeed, "server").optString("inviteCode"), "");
        if (!code.isEmpty()) message.append("\nکد دعوت: ").append(code);
        else message.append("\nکد دعوت هنوز فعال نشده است.");
        String link = safeText(object(publicFeed, "links").optString("community"), "");
        if (!link.isEmpty()) message.append("\nانجمن: ").append(link);
        shareText(message.toString(), "دعوت دوستان");
        unlockBadge("share");
    }

    private void shareText(String message, String chooserTitle) {
        try {
            Intent intent = new Intent(Intent.ACTION_SEND);
            intent.setType("text/plain");
            intent.putExtra(Intent.EXTRA_TEXT, message);
            startActivity(Intent.createChooser(intent, chooserTitle));
        } catch (Exception error) {
            toast("برنامه‌ای برای اشتراک‌گذاری پیدا نشد.");
        }
    }

    private void copyServerAddress() {
        if (!hasServerAddress()) {
            toast("آدرس و پورت رسمی هنوز تنظیم نشده‌اند.");
            return;
        }
        copyText(displayAddress(), "آدرس سرور کپی شد");
    }

    private void copyText(String value, String message) {
        ClipboardManager clipboard = (ClipboardManager) getSystemService(Context.CLIPBOARD_SERVICE);
        if (clipboard != null) {
            clipboard.setPrimaryClip(ClipData.newPlainText("Arvan Gaming", value));
            toast(message);
        }
    }

    private void openHttpsUrl(String value) {
        if (value == null || !value.trim().startsWith("https://")) {
            toast("پیوند HTTPS رسمی هنوز ثبت نشده است.");
            return;
        }
        try {
            startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(value.trim())));
        } catch (Exception error) {
            toast("مرورگر در دسترس نیست.");
        }
    }

    private void setAnimatedClick(final View view, final Runnable action) {
        view.setClickable(true);
        view.setOnClickListener(new View.OnClickListener() {
            @Override public void onClick(final View tapped) {
                tapped.performHapticFeedback(android.view.HapticFeedbackConstants.VIRTUAL_KEY);
                tapped.animate().cancel();
                tapped.setScaleX(1f);
                tapped.setScaleY(1f);
                tapped.animate().scaleX(0.94f).scaleY(0.94f).setDuration(80)
                        .withEndAction(new Runnable() {
                            @Override public void run() {
                                tapped.animate().scaleX(1.035f).scaleY(1.035f)
                                        .setInterpolator(new android.view.animation.OvershootInterpolator(1.7f))
                                        .setDuration(210)
                                        .withEndAction(new Runnable() {
                                            @Override public void run() {
                                                tapped.animate().scaleX(1f).scaleY(1f).setDuration(90).start();
                                                if (action != null) action.run();
                                            }
                                        }).start();
                            }
                        }).start();
            }
        });
    }

    private void updateNavigationColors() {
        if (navItems == null || navIcons == null || navContainers == null) return;
        int selected = 0;
        if ("events".equals(currentPage)) selected = 1;
        else if ("profile".equals(currentPage)) selected = 2;
        else if (!("home".equals(currentPage))) selected = 3;
        final int activeIndex = selected;
        for (int i = 0; i < navItems.length; i++) {
            boolean active = i == activeIndex;
            int activeColor = active ? PURPLE_DARK : MUTED;
            navItems[i].setTextColor(activeColor);
            navIcons[i].setTextColor(active ? PURPLE : MUTED);
            navItems[i].setTypeface(Typeface.create("sans-serif", active ? Typeface.BOLD : Typeface.NORMAL));
            navContainers[i].setBackground(rounded(active ? PURPLE_LIGHT : Color.TRANSPARENT, 19, 0));
            if (active) {
                navContainers[i].animate().scaleX(1.04f).scaleY(1.04f).setDuration(150)
                        .withEndAction(new Runnable() {
                            @Override public void run() {
                                navContainers[activeIndex].animate().scaleX(1f).scaleY(1f).setDuration(150).start();
                            }
                        }).start();
            } else {
                navContainers[i].setScaleX(1f);
                navContainers[i].setScaleY(1f);
            }
        }
    }

    private void startCountdownTicker() {
        mainHandler.removeCallbacks(countdownRunnable);
        if (homeCountdown != null && homeCountdownAt > 0L) mainHandler.post(countdownRunnable);
    }

    private JSONObject nextEvent() {
        JSONArray events = array(publicFeed, "events");
        JSONObject next = null;
        long now = System.currentTimeMillis();
        long nearest = Long.MAX_VALUE;
        for (int i = 0; i < events.length(); i++) {
            JSONObject item = events.optJSONObject(i);
            if (item == null) continue;
            long startsAt = item.optLong("startsAt", 0L);
            if (startsAt > now && startsAt < nearest) {
                nearest = startsAt;
                next = item;
            }
        }
        return next;
    }

    private String formatCountdown(long target) {
        long difference = target - System.currentTimeMillis();
        if (difference <= 0) return "رویداد آغاز شده است";
        long seconds = difference / 1000L;
        long days = seconds / 86400L;
        long hours = (seconds % 86400L) / 3600L;
        long minutes = (seconds % 3600L) / 60L;
        long remainingSeconds = seconds % 60L;
        if (days > 0) return days + " روز  " + hours + " ساعت مانده";
        return String.format(Locale.getDefault(), "%02d:%02d:%02d مانده", hours, minutes, remainingSeconds);
    }

    private String formatDate(long timestamp) {
        try {
            SimpleDateFormat format = new SimpleDateFormat("EEEE d MMMM، HH:mm", new Locale("fa", "IR"));
            return format.format(new Date(timestamp));
        } catch (Exception ignored) {
            return String.valueOf(timestamp);
        }
    }

    private String[] supportedVersions() {
        JSONArray remote = array(object(publicFeed, "server"), "supportedVersions");
        if (remote.length() > 0) {
            String[] versions = new String[remote.length()];
            for (int i = 0; i < remote.length(); i++) versions[i] = remote.optString(i, "");
            return versions;
        }
        return ArvanGamingConfig.SUPPORTED_CLIENT_VERSIONS;
    }

    private String supportedVersionsText() {
        String[] versions = supportedVersions();
        if (versions.length == 0) return "نسخه‌های سازگار هنوز از طرف مدیریت تأیید و منتشر نشده‌اند.";
        StringBuilder result = new StringBuilder("نسخه‌های اعلام‌شده: ");
        for (int i = 0; i < versions.length; i++) {
            if (i > 0) result.append("، ");
            result.append(versions[i]);
        }
        return result.toString();
    }

    private String serverHost() {
        JSONObject server = object(publicFeed, "server");
        String host = safeText(server.optString("address"), ArvanGamingConfig.SERVER_HOST);
        return host == null ? "" : host.trim();
    }

    private int serverPort() {
        JSONObject server = object(publicFeed, "server");
        int port = server.optInt("port", ArvanGamingConfig.SERVER_PORT);
        return port > 0 && port <= 65535 ? port : 0;
    }

    private boolean hasServerAddress() {
        return !serverHost().isEmpty() && serverPort() > 0;
    }

    private String displayAddress() {
        return hasServerAddress() ? serverHost() + ":" + serverPort() : "";
    }

    private String statusLabel() {
        if ("online".equals(pingState)) return "آنلاین";
        if ("checking".equals(pingState)) return "در حال بررسی";
        if ("unconfigured".equals(pingState) || !hasServerAddress()) return "تنظیم نشده";
        if (lastStatusCheckedAt > 0) return "بی‌پاسخ / نامشخص";
        return "نامشخص";
    }

    private int pingColor() {
        if ("online".equals(pingState)) return GREEN;
        if ("checking".equals(pingState)) return ORANGE;
        if ("unconfigured".equals(pingState) || !hasServerAddress()) return MUTED;
        return PINK;
    }

    private JSONArray array(JSONObject object, String key) {
        if (object == null) return new JSONArray();
        JSONArray array = object.optJSONArray(key);
        return array == null ? new JSONArray() : array;
    }

    private JSONObject object(JSONObject object, String key) {
        if (object == null) return new JSONObject();
        JSONObject value = object.optJSONObject(key);
        return value == null ? new JSONObject() : value;
    }

    private String safeText(String value, String fallback) {
        if (value == null || value.trim().isEmpty() || "null".equalsIgnoreCase(value.trim())) return fallback == null ? "" : fallback;
        return value.trim();
    }

    private String compactText(String value, int maxLength) {
        String text = value == null ? "" : value.trim();
        if (text.length() <= maxLength) return text;
        return text.substring(0, Math.max(0, maxLength - 1)).trim() + "…";
    }

    private boolean hasAnyOfficialLink(JSONObject links) {
        return nonEmpty(links.optString("support")) || nonEmpty(links.optString("telegram"))
                || nonEmpty(links.optString("discord")) || nonEmpty(links.optString("instagram"))
                || nonEmpty(ArvanGamingConfig.SUPPORT_URL) || nonEmpty(ArvanGamingConfig.TELEGRAM_URL)
                || nonEmpty(ArvanGamingConfig.DISCORD_URL) || nonEmpty(ArvanGamingConfig.INSTAGRAM_URL);
    }

    private boolean nonEmpty(String value) {
        return value != null && !value.trim().isEmpty() && !"null".equalsIgnoreCase(value.trim());
    }

    private boolean hasBadge(String key) {
        return preferences.getBoolean("badge_" + key, false);
    }

    private void unlockBadge(String key) {
        if (!hasBadge(key)) preferences.edit().putBoolean("badge_" + key, true).apply();
    }

    private void pageTitle(LinearLayout column, String title, String subtitle, int accent) {
        column.addView(label(title, 23, TEXT, true), matchWrap());
        space(column, 4);
        column.addView(label(subtitle, 11, accent, false), matchWrap());
    }

    private LinearLayout card() {
        LinearLayout layout = new LinearLayout(this);
        layout.setOrientation(LinearLayout.VERTICAL);
        layout.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        layout.setPadding(dp(17), dp(16), dp(17), dp(16));
        layout.setBackground(rounded(PANEL, 21, BORDER));
        if (android.os.Build.VERSION.SDK_INT >= 21) layout.setElevation(dp(3));
        return layout;
    }

    private LinearLayout horizontal() {
        LinearLayout row = new LinearLayout(this);
        row.setOrientation(LinearLayout.HORIZONTAL);
        row.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        return row;
    }

    private TextView label(String value, float size, int color, boolean bold) {
        TextView view = new TextView(this);
        view.setText(value == null ? "" : value);
        view.setTextSize(size);
        view.setTextColor(color);
        view.setGravity(Gravity.RIGHT | Gravity.CENTER_VERTICAL);
        view.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        view.setTypeface(Typeface.create("sans-serif", bold ? Typeface.BOLD : Typeface.NORMAL));
        view.setLineSpacing(dp(1), 1f);
        return view;
    }

    private TextView centered(String value, float size, int color, boolean bold) {
        TextView view = label(value, size, color, bold);
        view.setGravity(Gravity.CENTER);
        return view;
    }

    private TextView button(String value, int background, int textColor, boolean bold) {
        TextView view = centered(value, 12, textColor, bold);
        view.setPadding(dp(15), dp(10), dp(15), dp(10));
        view.setMinHeight(dp(45));
        int stroke = (background == PANEL_HI || background == PANEL || background == Color.WHITE
                || Color.luminance(background) > 0.88f) ? BORDER : 0;
        GradientDrawable shape = rounded(background, 15, stroke);
        if (android.os.Build.VERSION.SDK_INT >= 21) {
            int rippleColor = (background == PURPLE || background == PURPLE_DARK)
                    ? 0x33ffffff : 0x227c51d2;
            view.setBackground(new android.graphics.drawable.RippleDrawable(
                    android.content.res.ColorStateList.valueOf(rippleColor), shape, null));
            view.setElevation(dp(1));
        } else {
            view.setBackground(shape);
        }
        return view;
    }

    private TextView pill(String value, int color, int background) {
        TextView view = centered(value, 9, color, true);
        view.setPadding(dp(10), dp(7), dp(10), dp(7));
        view.setBackground(rounded(background, 20, 0));
        return view;
    }

    private int withAlpha(int color, float alpha) {
        return Color.argb(Math.round(255f * alpha), Color.red(color), Color.green(color), Color.blue(color));
    }

    private GradientDrawable rounded(int color, int radius, int stroke) {
        GradientDrawable shape = new GradientDrawable();
        shape.setColor(color);
        shape.setCornerRadius(dp(radius));
        if (stroke != 0) shape.setStroke(dp(1), stroke);
        return shape;
    }

    private GradientDrawable gradient(int[] colors, int radius, int stroke) {
        GradientDrawable shape = new GradientDrawable(GradientDrawable.Orientation.TL_BR, colors);
        shape.setCornerRadius(dp(radius));
        if (stroke != 0) shape.setStroke(dp(1), stroke);
        return shape;
    }

    private void addColumn(LinearLayout parent, View child, int topMargin) {
        LinearLayout.LayoutParams params = matchWrap();
        params.topMargin = dp(topMargin);
        parent.addView(child, params);
    }

    private LinearLayout.LayoutParams matchWrap() {
        return new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT);
    }

    private LinearLayout.LayoutParams wrap() {
        return new LinearLayout.LayoutParams(ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT);
    }

    private void space(LinearLayout parent, int height) {
        parent.addView(new View(this), new LinearLayout.LayoutParams(1, dp(height)));
    }

    private int dp(float value) {
        return (int) (value * getResources().getDisplayMetrics().density + 0.5f);
    }

    private void toast(String message) {
        Toast.makeText(this, message, Toast.LENGTH_LONG).show();
    }

    private static final class SearchItem {
        final String title;
        final String subtitle;
        final String icon;
        final int accent;
        final String destination;
        final boolean featured;

        SearchItem(String title, String subtitle, String icon, int accent, String destination, boolean featured) {
            this.title = title;
            this.subtitle = subtitle;
            this.icon = icon;
            this.accent = accent;
            this.destination = destination;
            this.featured = featured;
        }
    }
}
