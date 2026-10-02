package ir.minecraftacademy.app;

import android.app.Activity;
import android.app.AlertDialog;
import android.content.Context;
import android.content.SharedPreferences;
import android.graphics.Canvas;
import android.graphics.Color;
import android.graphics.LinearGradient;
import android.graphics.Paint;
import android.graphics.Path;
import android.graphics.Shader;
import android.graphics.Typeface;
import android.graphics.drawable.GradientDrawable;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.Gravity;
import android.view.View;
import android.view.ViewGroup;
import android.view.WindowManager;
import android.view.inputmethod.EditorInfo;
import android.widget.EditText;
import android.widget.FrameLayout;
import android.widget.HorizontalScrollView;
import android.widget.LinearLayout;
import android.widget.ScrollView;
import android.widget.TextView;
import android.widget.Toast;

import java.util.ArrayList;
import java.util.HashSet;
import java.util.List;
import java.util.Locale;
import java.util.Set;

/**
 * مکعب‌آموز؛ راهنمای آفلاین و فارسی برای یادگیری Minecraft.
 * UI and voxel-style illustrations are drawn locally; there are no external dependencies.
 */
public class MainActivity extends Activity {
    private static final int BG = Color.rgb(15, 22, 17);
    private static final int PANEL = Color.rgb(26, 37, 29);
    private static final int PANEL_LIGHT = Color.rgb(35, 49, 38);
    private static final int BORDER = Color.rgb(49, 66, 52);
    private static final int TEXT = Color.rgb(240, 245, 238);
    private static final int MUTED = Color.rgb(164, 179, 164);
    private static final int GREEN = Color.rgb(154, 232, 92);
    private static final int GREEN_DARK = Color.rgb(35, 69, 41);
    private static final int GOLD = Color.rgb(255, 204, 91);
    private static final int RED = Color.rgb(242, 112, 104);

    private final List<Lesson> lessons = new ArrayList<Lesson>();
    private final Set<Integer> completed = new HashSet<Integer>();
    private final String[][] glossary = new String[][]{
            {"میز ساخت (Crafting Table)", "میز ۳×۳ برای ساخت بیشتر ابزارها، بلوک‌ها و وسایل.", "ساخت"},
            {"تخته چوبی (Planks)", "از چوب خام ساخته می‌شود و مادهٔ پایهٔ بسیاری از دستورهای ساخت است.", "ساخت"},
            {"چوب‌دستی (Stick)", "با قرار دادن دو تخته به‌صورت عمودی ساخته می‌شود؛ برای ابزار و مشعل لازم است.", "ساخت"},
            {"کلنگ (Pickaxe)", "ابزار کندن سنگ و معدن. جنس کلنگ تعیین می‌کند چه بلوکی را بتوانی جمع کنی.", "ابزار"},
            {"مشعل (Torch)", "با چوب‌دستی و زغال یا زغال‌چوب ساخته می‌شود؛ مسیر را روشن می‌کند.", "بقا"},
            {"زغال‌چوب (Charcoal)", "با پختن تنهٔ درخت در کوره به دست می‌آید و جایگزین زغال برای مشعل است.", "معدن"},
            {"کوره (Furnace)", "برای پخت غذا و ذوب بعضی سنگ‌ها و مواد استفاده می‌شود.", "ساخت"},
            {"میزان گرسنگی", "با دویدن و کارهای مختلف کم می‌شود؛ غذا بخور تا سلامتی‌ات بازیابی شود.", "بقا"},
            {"مختصات (Coordinates)", "عددهای X، Y و Z جای تو را در جهان نشان می‌دهند. Y ارتفاع را مشخص می‌کند.", "اکتشاف"},
            {"ردستون (Redstone)", "گرد قرمز برای ساخت مدارها و مکانیزم‌های ساده و پیشرفته.", "ردستون"},
            {"تکرارکننده (Repeater)", "سیگنال ردستون را دوباره تقویت می‌کند و می‌توان با آن تأخیر ساخت.", "ردستون"},
            {"مشاهده‌گر (Observer)", "تغییر بلوک جلوی خود را تشخیص می‌دهد و یک پالس ردستون می‌فرستد.", "ردستون"},
            {"اوبسیدین (Obsidian)", "بلوک سختی که با آب و لاوا ساخته می‌شود؛ برای درگاه ندر کاربرد دارد.", "اکتشاف"},
            {"چشم اندر (Eye of Ender)", "با پودر بلیز و مروارید اندر ساخته می‌شود و مسیر دژ را نشان می‌دهد.", "اکتشاف"},
            {"تخم گندم", "با شکستن علف به دست می‌آید؛ بکارش تا گندم و بعد نان داشته باشی.", "کشاورزی"},
            {"سپر (Shield)", "در برابر بسیاری از حمله‌ها محافظت می‌کند؛ هنگام استفاده، حرکتت کندتر می‌شود.", "نبرد"}
    };

    private SharedPreferences preferences;
    private LinearLayout root;
    private FrameLayout screenHost;
    private LinearLayout bottomBar;
    private String route = "home";
    private String returnRoute = "home";
    private String selectedFilter = "همه";
    private int selectedLesson = 0;
    private int selectedAnswer = -1;
    private boolean quizSubmitted = false;
    private boolean justEarnedXp = false;

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        getWindow().setStatusBarColor(BG);
        getWindow().setNavigationBarColor(BG);
        getWindow().setSoftInputMode(WindowManager.LayoutParams.SOFT_INPUT_ADJUST_RESIZE);

        preferences = getSharedPreferences("cube_academy_progress", MODE_PRIVATE);
        loadProgress();
        createLessons();

        root = new LinearLayout(this);
        root.setOrientation(LinearLayout.VERTICAL);
        root.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        root.setBackgroundColor(BG);
        setContentView(root);

        screenHost = new FrameLayout(this);
        root.addView(screenHost, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, 0, 1f));
        bottomBar = new LinearLayout(this);
        bottomBar.setOrientation(LinearLayout.VERTICAL);
        root.addView(bottomBar, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, dp(74)));
        render();
    }

    private void createLessons() {
        lessons.add(new Lesson(0, "نجات از شب اول", "بقا", "🌙", "۶ دقیقه",
                "روز اول کوتاه‌تر از چیزی است که فکر می‌کنی. پیش از تاریک شدن هوا، چوب جمع کن، چند ابزار ساده بساز و یک پناهگاه امن آماده کن.",
                new String[]{
                        "چند تنه درخت جمع کن و آن‌ها را در بخش ساخت به تخته چوبی تبدیل کن.",
                        "با چهار تخته، میز ساخت بساز؛ سپس با تخته و چوب‌دستی یک کلنگ چوبی درست کن.",
                        "از سنگ‌های نزدیک سطح زمین، ابزار سنگی و یک کوره بساز تا کارت سریع‌تر شود.",
                        "پیش از غروب یک اتاق کوچک ببند. در ورودی بگذار و داخل و اطرافش را با مشعل روشن کن.",
                        "غذا پیدا کن و نوار گرسنگی را زیر نظر داشته باش؛ گرسنگی روی بازیابی سلامتی اثر می‌گذارد."
                },
                "لازم نیست روز اول خانهٔ رؤیایی بسازی؛ یک اتاق کوچکِ روشن و دردار، شب اول را امن‌تر می‌کند.",
                "پیش از تاریکی، کدام کار برای تازه‌کار مهم‌تر است؟",
                new String[]{"ساخت پناهگاه ساده و روشن", "رفتن به عمیق‌ترین بخش معدن", "ساختن قلعه‌ای بزرگ"}, 0,
                "برای شروع، پناهگاه کوچک و نور کافی خطر روبه‌رو شدن با موجودات شب را کمتر می‌کند."));

        lessons.add(new Lesson(1, "میز ساخت و ابزارها", "ساخت", "🛠️", "۵ دقیقه",
                "دستورهای ساخت، زبان اصلی دنیای ماینکرفت‌اند. با چند مادهٔ ساده می‌توانی از ابزار ابتدایی به ابزارهای بهتر برسی.",
                new String[]{
                        "تنهٔ درخت را در بخش ساخت بازیکن قرار بده تا تختهٔ چوبی بگیری.",
                        "دو تخته را عمودی بچین تا چوب‌دستی بسازی؛ چهار تخته هم یک میز ساخت می‌دهد.",
                        "روی میز ساخت، سه تخته را در ردیف بالا و دو چوب‌دستی را در ستون وسط بگذار تا کلنگ چوبی ساخته شود.",
                        "کلنگ چوبی برای جمع‌کردن سنگ مناسب است؛ بعد با سنگ، کلنگ سنگی بساز و ابزار چوبی را ارتقا بده.",
                        "جنس ابزار مهم است: برای جمع‌کردن بعضی کانسنگ‌ها باید کلنگی با جنس مناسب داشته باشی."
                },
                "اگر دستور ساخت را یادت نمی‌آید، دفترچهٔ دستورها داخل بازی را باز کن؛ دستورها با ماده‌هایی که پیدا کرده‌ای فیلتر می‌شوند.",
                "برای ساخت کلنگ چوبی، تخته‌ها و چوب‌دستی‌ها را چطور می‌چینی؟",
                new String[]{"سه تخته بالا؛ دو چوب‌دستی در ستون وسط", "دو تخته در گوشه‌ها و یک مشعل وسط", "همهٔ مواد را در یک خانه می‌گذارم"}, 0,
                "در الگوی معمول کلنگ، سه مادهٔ سازنده در ردیف بالا و دو چوب‌دستی در میانهٔ ستون مرکزی قرار می‌گیرند."));

        lessons.add(new Lesson(2, "پناهگاه و نورپردازی", "ساخت", "🏡", "۵ دقیقه",
                "یک خانهٔ خوب فقط دیوار نیست. جای مناسب، نورپردازی، ورودی امن و چند جزئیات کوچک، پایگاهت را کاربردی و جذاب می‌کند.",
                new String[]{
                        "برای شروع، نزدیک چوب و آب خانه بساز؛ اما دهانهٔ غار یا مسیر رفت‌وآمد موجودات را انتخاب نکن.",
                        "یک قاب ساده از چوب یا سنگ بساز و برای ورودی، جای در و پنجره در نظر بگیر.",
                        "داخل اتاق و مسیر اطراف خانه را روشن کن. گوشه‌های تاریک را فراموش نکن.",
                        "صندوق، تخت و کوره را جایی بگذار که راحت به آن‌ها دسترسی داشته باشی.",
                        "برای ظاهر بهتر از دو یا سه جنس بلوک استفاده کن؛ پله، حصار و شیشه به خانه عمق می‌دهند."
                },
                "یک نقطهٔ دیدبانی کوچک روی سقف یا کنار در ورودی، پیدا کردن خانه را از دور آسان‌تر می‌کند.",
                "چرا روشن‌کردن داخل و اطراف خانه مهم است؟",
                new String[]{"برای سریع‌تر رشد کردن درخت‌ها", "برای دید بهتر و امن‌تر شدن محیط", "چون مشعل دیوارها را محکم می‌کند"}, 1,
                "نور، دید را بهتر می‌کند و در شرایط معمول، محل‌های تاریک را برای پیدایش برخی موجودات نامناسب‌تر می‌سازد."));

        lessons.add(new Lesson(3, "غذا و مزرعهٔ کوچک", "بقا", "🌾", "۶ دقیقه",
                "با یک مزرعهٔ کوچک، منبع غذای قابل‌اعتمادتری می‌سازی. گندم‌کاری ساده است و به فضای بزرگی احتیاج ندارد.",
                new String[]{
                        "با شکستن علف‌های بلند، تخم گندم جمع کن؛ چند دانه برای شروع کافی است.",
                        "با بیل، خاک را شخم بزن و زمین را نزدیک آب انتخاب کن تا مرطوب بماند.",
                        "تخم‌ها را روی خاک شخم‌خورده بکار و از روی محصول تازه عبور نکن تا خراب نشود.",
                        "وقتی گندم رسیده شد، آن را برداشت کن و بخشی از دانه‌ها را دوباره بکار.",
                        "سه گندم در ردیف افقی میز ساخت، نان می‌دهد. مزرعه را با حصار از حیوانات دور نگه دار."
                },
                "زمین کشاورزی تا فاصلهٔ چهار بلوک افقی از منبع آب می‌تواند مرطوب شود؛ مزرعه را دور آب بچین.",
                "کدام کار به مرطوب ماندن خاک مزرعه کمک می‌کند؟",
                new String[]{"گذاشتن کوره کنار زمین", "کاشت دانه روی سنگ", "ساخت مزرعه نزدیک منبع آب"}, 2,
                "آب نزدیک، زمین شخم‌خورده را مرطوب می‌کند؛ برای مزرعهٔ کوچک یک کانال آب کافی است."));

        lessons.add(new Lesson(4, "معدن‌کاوی امن", "معدن", "⛏️", "۷ دقیقه",
                "معدن مواد ارزشمندی دارد، اما تاریکی و سقوط خطرناک‌اند. با ابزار مناسب، مشعل و یک مسیر حساب‌شده، امن‌تر معدن‌کاوی کن.",
                new String[]{
                        "غذا، کلنگ مناسب، مشعل و چند بلوک اضافی بردار؛ اگر سطل آب داری، همراهت باشد.",
                        "هیچ‌وقت مستقیم زیر پایت را نکن؛ پله‌ای پایین برو تا در لاوا یا غار پنهان سقوط نکنی.",
                        "مشعل‌ها را در فاصله‌های منظم بگذار و مسیر برگشت را با یک الگوی ثابت نشانه‌گذاری کن.",
                        "برای کانسنگ‌ها کلنگ مناسب به‌کار ببر؛ بعضی بلوک‌ها با ابزار ضعیف‌تر چیزی نمی‌دهند.",
                        "مختصات را بررسی کن. جای دقیق کانسنگ‌ها در نسخه‌های مختلف فرق می‌کند؛ تونل‌زنی را با احتیاط انجام بده."
                },
                "پیش از رفتن به معدن، یک صندوق کنار ورودی بگذار تا وسایل اضافه و مواد یافته‌شده را مرتب کنی.",
                "کدام روش برای پایین‌رفتن در معدن امن‌تر است؟",
                new String[]{"کندن مستقیم زیر پا", "کندن پله‌ای و روشن‌کردن مسیر", "دویدن در تونل تاریک"}, 1,
                "پله‌سازی احتمال سقوط ناگهانی را کم می‌کند و مشعل‌ها کمک می‌کنند مسیر برگشت را پیدا کنی."));

        lessons.add(new Lesson(5, "زره و نبرد", "بقا", "🛡️", "۶ دقیقه",
                "برای ماجراجویی طولانی، زره و غذا به‌اندازهٔ شمشیر اهمیت دارند. قبل از درگیری، فاصله، سپر و راه فرار را هم در نظر بگیر.",
                new String[]{
                        "از چوب و سنگ شروع کن، اما برای سفرهای سخت‌تر زره و ابزار بهتر بساز.",
                        "سپر را بساز و در دست فرعی قرار بده؛ زمان‌بندی دفاع در برابر پرتابه‌ها مهم است.",
                        "برای دشمنان دوربرد، کمان و تیر کمک می‌کند. با عجله وارد چند درگیری هم‌زمان نشو.",
                        "غذا و زره اضافه همراه داشته باش و قبل از رفتن، نقطهٔ بازگشت یا پایگاهت را به خاطر بسپار.",
                        "اگر سلامتی کم شد، عقب‌نشینی کن و در جای امن غذا بخور؛ ادامه‌دادن هر نبردی لازم نیست."
                },
                "یک سپر می‌تواند جلوی بسیاری از حمله‌های روبه‌رو را بگیرد؛ هنگام دفاع، اطرافت را هم بررسی کن.",
                "سپر را معمولاً برای چه کاری همراه می‌بری؟",
                new String[]{"روشن‌کردن معدن", "ساختن درگاه ندر", "دفاع در برابر بسیاری از حمله‌ها"}, 2,
                "سپر ابزار دفاعی است؛ برای معدن، روشنایی از مشعل می‌آید و درگاه ندر از اوبسیدین ساخته می‌شود."));

        lessons.add(new Lesson(6, "ردستون؛ مدار اول", "ردستون", "🔴", "۷ دقیقه",
                "ردستون به تو اجازه می‌دهد اتفاق‌های دنیای بازی را به هم وصل کنی. با یک اهرم، گرد ردستون و در، مدار ساده‌ات را روشن کن.",
                new String[]{
                        "گرد ردستون را از کانسنگ ردستون به دست بیاور و آن را روی بلوک مناسب قرار بده.",
                        "اهرم یا دکمه منبع نیرو است؛ وقتی آن را فعال کنی، سیگنال به گرد ردستون می‌رسد.",
                        "درِ چوبی را کنار مدار بگذار تا با اهرم باز و بسته شود. جهت قرارگیری بعضی قطعات مهم است.",
                        "تکرارکننده سیگنال را تقویت می‌کند و می‌تواند برای مدار تأخیر بسازد.",
                        "مرحلهٔ بعدی را با مشاهده‌گر امتحان کن: تغییر بلوک روبه‌رویش می‌تواند پالس تولید کند."
                },
                "اول مدار را روی زمین باز و ساده بساز؛ وقتی کار کرد، آن را زیرزمین یا پشت دیوار پنهان کن.",
                "کدام قطعه برای افزودن تأخیر به سیگنال ردستون کاربرد دارد؟",
                new String[]{"تخت", "تکرارکننده (Repeater)", "سطل آب"}, 1,
                "تکرارکننده می‌تواند بین ورودی و خروجی مدار تأخیر ایجاد کند و در مسیرهای طولانی سیگنال را تازه کند."));

        lessons.add(new Lesson(7, "راه تا اژدهای اِنْد", "ماجراجویی", "🐉", "۸ دقیقه",
                "رسیدن به اِنْد یک سفر مرحله‌به‌مرحله است: آماده‌سازی، پیدا کردن دژ، ورود به اِنْد و بعد روبه‌روشدن با اژدها.",
                new String[]{
                        "زره، غذا، کمان و بلوک بساز. پیش از رفتن، وسایل ارزشمند اضافه را در صندوق خانه بگذار.",
                        "با پودر بلیز و مروارید اندر، چشم اندر بساز. چند چشم اضافه همراه داشته باش؛ بعضی ممکن است بشکنند.",
                        "چشم اندر را پرتاب کن و جهت حرکتش را دنبال کن تا به محدودهٔ دژ برسی؛ مختصات را یادداشت کن.",
                        "در اتاق درگاه، قاب‌ها را کامل کن و پس از آماده‌شدن، وارد اِنْد شو.",
                        "از فاصله، کریستال‌های برج‌ها را هدف بگیر؛ وقتی اژدها پایین می‌آید، از فرصت حمله استفاده کن."
                },
                "این سفر طولانی است؛ نسخهٔ بازی می‌تواند روی جزئیات تفاوت داشته باشد. پیش از ورود، راهنمای دستورهای ساخت و تنظیمات جهان را بررسی کن.",
                "چشم اندر بیشتر برای پیدا کردن چه چیزی به کار می‌رود؟",
                new String[]{"معدن زغال‌سنگ", "مزرعهٔ گندم", "دژ و مسیر درگاه اِنْد"}, 2,
                "چشم اندر مسیر دژ را نشان می‌دهد؛ برای سفر نهایی تجهیزات، غذا و چند چشم اضافه همراه داشته باش."));
    }

    private void loadProgress() {
        String saved = getSharedPreferences("cube_academy_progress", MODE_PRIVATE)
                .getString("completed_lessons", "");
        if (saved.length() == 0) return;
        String[] parts = saved.split(",");
        for (int i = 0; i < parts.length; i++) {
            try {
                int id = Integer.parseInt(parts[i]);
                if (id >= 0 && id < 8) completed.add(id);
            } catch (NumberFormatException ignored) {
            }
        }
    }

    private void saveProgress() {
        StringBuilder value = new StringBuilder();
        for (int i = 0; i < lessons.size(); i++) {
            if (completed.contains(i)) {
                if (value.length() > 0) value.append(',');
                value.append(i);
            }
        }
        preferences.edit().putString("completed_lessons", value.toString()).apply();
    }

    private void render() {
        View screen;
        if ("lessons".equals(route)) screen = buildLessons();
        else if ("glossary".equals(route)) screen = buildGlossary();
        else if ("progress".equals(route)) screen = buildProgress();
        else if ("detail".equals(route)) screen = buildLessonDetail();
        else if ("quiz".equals(route)) screen = buildQuiz();
        else screen = buildHome();

        screenHost.removeAllViews();
        screenHost.addView(screen, new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));
        refreshBottomBar();
    }

    private LinearLayout pageColumn() {
        LinearLayout column = new LinearLayout(this);
        column.setOrientation(LinearLayout.VERTICAL);
        column.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        column.setPadding(dp(18), dp(12), dp(18), dp(24));
        column.setBackgroundColor(BG);
        return column;
    }

    private ScrollView wrapPage(LinearLayout column) {
        ScrollView scroll = new ScrollView(this);
        scroll.setFillViewport(true);
        scroll.setVerticalScrollBarEnabled(false);
        scroll.setOverScrollMode(View.OVER_SCROLL_IF_CONTENT_SCROLLS);
        scroll.setBackgroundColor(BG);
        scroll.addView(column, new ScrollView.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        return scroll;
    }

    private View buildHome() {
        LinearLayout column = pageColumn();
        addHomeHeader(column);
        addSpace(column, 18);
        column.addView(buildHero(), new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, dp(224)));
        addSpace(column, 14);
        column.addView(buildStats(), new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, dp(84)));
        addSpace(column, 24);
        addSectionHeading(column, "مسیر ماجراجویی", "پیشرفت", null);
        addSpace(column, 10);
        column.addView(buildJourneyCard(), new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        addSpace(column, 26);
        addSectionHeading(column, "نقشهٔ یادگیری", "همهٔ درس‌ها", new Runnable() {
            @Override public void run() { route = "lessons"; render(); }
        });
        addSpace(column, 12);
        addLessonGrid(column, lessons);
        addSpace(column, 18);
        column.addView(buildOfflineNote(), new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        return wrapPage(column);
    }

    private void addHomeHeader(LinearLayout column) {
        LinearLayout row = horizontal();
        row.setGravity(Gravity.CENTER_VERTICAL);
        CubeLogo logo = new CubeLogo(this);
        row.addView(logo, new LinearLayout.LayoutParams(dp(46), dp(46)));

        LinearLayout brand = new LinearLayout(this);
        brand.setOrientation(LinearLayout.VERTICAL);
        brand.setPadding(0, 0, dp(10), 0);
        brand.addView(label("مکعب‌آموز", 18, TEXT, true));
        TextView sub = label("آکادمی فارسی ماینکرفت", 11, MUTED, false);
        brand.addView(sub);
        row.addView(brand, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT));

        View spacer = new View(this);
        row.addView(spacer, new LinearLayout.LayoutParams(0, 1, 1f));
        TextView xp = centeredLabel("⚡  " + xp() + " XP", 12, GOLD, true);
        xp.setPadding(dp(12), dp(9), dp(12), dp(9));
        xp.setBackground(rounded(PANEL_LIGHT, 30, BORDER));
        row.addView(xp, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        column.addView(row, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, dp(50)));
    }

    private View buildHero() {
        FrameLayout frame = new FrameLayout(this);
        frame.setBackground(rounded(PANEL, 24, BORDER));
        if (android.os.Build.VERSION.SDK_INT >= 21) frame.setClipToOutline(true);

        PixelSceneView scene = new PixelSceneView(this);
        frame.addView(scene, new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));

        View shade = new View(this);
        GradientDrawable fade = new GradientDrawable(GradientDrawable.Orientation.LEFT_RIGHT,
                new int[]{0x21101812, 0x74101812, 0xE8101812});
        shade.setBackground(fade);
        frame.addView(shade, new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT));

        LinearLayout text = new LinearLayout(this);
        text.setOrientation(LinearLayout.VERTICAL);
        text.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        text.setGravity(Gravity.CENTER_VERTICAL | Gravity.RIGHT);
        text.setPadding(dp(18), dp(16), dp(18), dp(16));
        TextView eyebrow = label("آماده‌ای، ماجراجو؟", 12, GREEN, true);
        text.addView(eyebrow);
        addSpace(text, 7);
        TextView title = label("از اولین شب\nتا اژدهای اِنْد", 23, TEXT, true);
        title.setMaxLines(2);
        title.setLineSpacing(dp(2), 1.0f);
        text.addView(title);
        addSpace(text, 7);
        TextView body = label("قدم‌به‌قدم بازی را یاد بگیر؛\nآفلاین و کاملاً فارسی.", 12, 0xFFD3DED2, false);
        body.setMaxLines(2);
        text.addView(body);
        addSpace(text, 12);
        TextView start = centeredLabel("شروع ماجراجویی  ←", 13, Color.rgb(22, 37, 24), true);
        start.setPadding(dp(16), dp(11), dp(16), dp(11));
        start.setBackground(rounded(GREEN, 14, 0));
        start.setClickable(true);
        text.addView(start, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        setClick(start, new Runnable() {
            @Override public void run() {
                openLesson(nextLesson());
            }
        });

        FrameLayout.LayoutParams textParams = new FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.MATCH_PARENT,
                Gravity.RIGHT | Gravity.CENTER_VERTICAL);
        frame.addView(text, textParams);
        return frame;
    }

    private View buildStats() {
        LinearLayout row = horizontal();
        row.setGravity(Gravity.CENTER_VERTICAL);
        row.setPadding(dp(8), dp(8), dp(8), dp(8));
        row.setBackground(rounded(PANEL, 19, BORDER));
        addStat(row, String.valueOf(lessons.size()), "درس کوتاه");
        addVerticalDivider(row);
        addStat(row, String.valueOf(completed.size()), "درس کامل‌شده");
        addVerticalDivider(row);
        addStat(row, String.valueOf(progressPercent()) + "%", "پیشرفت مسیر");
        return row;
    }

    private void addStat(LinearLayout row, String value, String caption) {
        LinearLayout cell = new LinearLayout(this);
        cell.setOrientation(LinearLayout.VERTICAL);
        cell.setGravity(Gravity.CENTER);
        TextView number = centeredLabel(value, 18, TEXT, true);
        TextView captionView = centeredLabel(caption, 10, MUTED, false);
        cell.addView(number);
        addSpace(cell, 3);
        cell.addView(captionView);
        row.addView(cell, new LinearLayout.LayoutParams(0, dp(62), 1f));
    }

    private void addVerticalDivider(LinearLayout row) {
        View divider = new View(this);
        divider.setBackgroundColor(BORDER);
        row.addView(divider, new LinearLayout.LayoutParams(dp(1), dp(34)));
    }

    private View buildJourneyCard() {
        final int next = nextLesson();
        final Lesson lesson = lessons.get(next);
        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.VERTICAL);
        card.setPadding(dp(16), dp(15), dp(16), dp(15));
        card.setBackground(rounded(PANEL, 20, BORDER));

        LinearLayout top = horizontal();
        top.setGravity(Gravity.CENTER_VERTICAL);
        TextView kicker = label(completed.size() == lessons.size() ? "همهٔ مسیرها بازند" : "قدم بعدی تو", 12, GREEN, true);
        top.addView(kicker, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        TextView status = centeredLabel(completed.contains(next) ? "✓ انجام‌شده" : lesson.duration, 11, MUTED, true);
        status.setPadding(dp(9), dp(6), dp(9), dp(6));
        status.setBackground(rounded(PANEL_LIGHT, 20, 0));
        top.addView(status);
        card.addView(top);
        addSpace(card, 10);
        TextView title = label(completed.size() == lessons.size() ? "آفرین! قهرمان شدی" : lesson.title, 18, TEXT, true);
        card.addView(title);
        addSpace(card, 5);
        TextView detail = label(completed.size() == lessons.size()
                ? "می‌توانی هر درس را دوباره مرور کنی یا دانشنامه را بگردی."
                : lesson.intro, 12, MUTED, false);
        detail.setMaxLines(2);
        detail.setEllipsize(android.text.TextUtils.TruncateAt.END);
        card.addView(detail);
        addSpace(card, 14);
        card.addView(new ProgressTrack(this, progressPercent() / 100f),
                new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(8)));
        addSpace(card, 13);
        TextView action = centeredLabel(completed.size() == lessons.size() ? "مرور درس‌ها  ←" : "ادامهٔ یادگیری  ←", 13,
                Color.rgb(22, 37, 24), true);
        action.setPadding(dp(15), dp(11), dp(15), dp(11));
        action.setBackground(rounded(GREEN, 13, 0));
        card.addView(action, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        setClick(card, new Runnable() {
            @Override public void run() {
                if (completed.size() == lessons.size()) {
                    route = "lessons";
                    render();
                } else {
                    openLesson(next);
                }
            }
        });
        return card;
    }

    private View buildOfflineNote() {
        LinearLayout card = horizontal();
        card.setGravity(Gravity.CENTER_VERTICAL);
        card.setPadding(dp(14), dp(12), dp(14), dp(12));
        card.setBackground(rounded(0xFF202C23, 16, BORDER));
        TextView icon = centeredLabel("✦", 20, GOLD, true);
        card.addView(icon, new LinearLayout.LayoutParams(dp(34), dp(34)));
        LinearLayout copy = new LinearLayout(this);
        copy.setOrientation(LinearLayout.VERTICAL);
        copy.setPadding(0, 0, dp(10), 0);
        copy.addView(label("یادگیری بدون اینترنت", 13, TEXT, true));
        copy.addView(label("پیشرفتت فقط روی همین دستگاه ذخیره می‌شود.", 11, MUTED, false));
        card.addView(copy, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        return card;
    }

    private View buildLessons() {
        LinearLayout column = pageColumn();
        addHomeHeader(column);
        addSpace(column, 22);
        addPageTitle(column, "کلاس‌های ماجراجویی", "یک درس کوتاه انتخاب کن و مهارت تازه یاد بگیر.");
        addSpace(column, 16);
        column.addView(buildMiniProgress(), new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        addSpace(column, 20);
        addSectionHeading(column, "دسته‌بندی", null, null);
        addSpace(column, 10);
        addFilterRow(column);
        addSpace(column, 17);
        List<Lesson> visible = new ArrayList<Lesson>();
        for (int i = 0; i < lessons.size(); i++) {
            Lesson lesson = lessons.get(i);
            if ("همه".equals(selectedFilter) || selectedFilter.equals(lesson.category)) visible.add(lesson);
        }
        addLessonGrid(column, visible);
        if (visible.size() == 0) {
            column.addView(label("در این دسته هنوز درسی نیست.", 13, MUTED, false));
        }
        return wrapPage(column);
    }

    private View buildMiniProgress() {
        LinearLayout box = new LinearLayout(this);
        box.setOrientation(LinearLayout.VERTICAL);
        box.setPadding(dp(15), dp(14), dp(15), dp(14));
        box.setBackground(rounded(PANEL, 18, BORDER));
        LinearLayout row = horizontal();
        row.setGravity(Gravity.CENTER_VERTICAL);
        row.addView(label("پیشرفت دوره", 13, TEXT, true),
                new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        row.addView(centeredLabel(completed.size() + " از " + lessons.size(), 12, GREEN, true));
        box.addView(row);
        addSpace(box, 11);
        box.addView(new ProgressTrack(this, progressPercent() / 100f),
                new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(8)));
        return box;
    }

    private void addFilterRow(LinearLayout column) {
        final String[] filters = new String[]{"همه", "بقا", "ساخت", "معدن", "ردستون", "ماجراجویی"};
        HorizontalScrollView horizontalScroll = new HorizontalScrollView(this);
        horizontalScroll.setHorizontalScrollBarEnabled(false);
        horizontalScroll.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        LinearLayout chips = horizontal();
        for (int i = 0; i < filters.length; i++) {
            final String filter = filters[i];
            TextView chip = centeredLabel(filter, 12,
                    filter.equals(selectedFilter) ? Color.rgb(20, 35, 22) : MUTED, true);
            chip.setPadding(dp(15), dp(9), dp(15), dp(9));
            chip.setBackground(rounded(filter.equals(selectedFilter) ? GREEN : PANEL, 22,
                    filter.equals(selectedFilter) ? 0 : BORDER));
            LinearLayout.LayoutParams chipParams = new LinearLayout.LayoutParams(
                    ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT);
            chipParams.rightMargin = dp(7);
            chips.addView(chip, chipParams);
            setClick(chip, new Runnable() {
                @Override public void run() { selectedFilter = filter; render(); }
            });
        }
        horizontalScroll.addView(chips, new HorizontalScrollView.LayoutParams(
                ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        column.addView(horizontalScroll, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
    }

    private void addLessonGrid(LinearLayout column, List<Lesson> items) {
        for (int i = 0; i < items.size(); i += 2) {
            LinearLayout row = horizontal();
            row.setGravity(Gravity.TOP);
            row.addView(createLessonCard(items.get(i)), new LinearLayout.LayoutParams(0,
                    ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
            View gap = new View(this);
            row.addView(gap, new LinearLayout.LayoutParams(dp(11), 1));
            if (i + 1 < items.size()) {
                row.addView(createLessonCard(items.get(i + 1)), new LinearLayout.LayoutParams(0,
                        ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
            } else {
                View empty = new View(this);
                row.addView(empty, new LinearLayout.LayoutParams(0, 1, 1f));
            }
            column.addView(row, new LinearLayout.LayoutParams(
                    ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
            addSpace(column, 11);
        }
    }

    private View createLessonCard(final Lesson lesson) {
        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.VERTICAL);
        card.setMinimumHeight(dp(164));
        card.setPadding(dp(12), dp(13), dp(12), dp(12));
        card.setBackground(rounded(PANEL, 18, BORDER));
        card.setClickable(true);

        LinearLayout top = horizontal();
        top.setGravity(Gravity.CENTER_VERTICAL);
        TextView icon = centeredLabel(lesson.emoji, 23, TEXT, false);
        icon.setBackground(rounded(lesson.tint, 14, 0));
        top.addView(icon, new LinearLayout.LayoutParams(dp(43), dp(43)));
        View spacer = new View(this);
        top.addView(spacer, new LinearLayout.LayoutParams(0, 1, 1f));
        TextView time = centeredLabel(lesson.duration, 10, MUTED, false);
        top.addView(time);
        card.addView(top);
        addSpace(card, 12);
        TextView category = label(lesson.category.toUpperCase(Locale.getDefault()), 10, GREEN, true);
        card.addView(category);
        addSpace(card, 5);
        TextView title = label(lesson.title, 15, TEXT, true);
        title.setMaxLines(2);
        title.setMinHeight(dp(40));
        card.addView(title);
        View fill = new View(this);
        card.addView(fill, new LinearLayout.LayoutParams(1, 0, 1f));
        LinearLayout footer = horizontal();
        footer.setGravity(Gravity.CENTER_VERTICAL);
        TextView status = label(completed.contains(lesson.id) ? "✓ تکمیل‌شده" : "شروع درس", 10,
                completed.contains(lesson.id) ? GREEN : MUTED, true);
        footer.addView(status, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        footer.addView(centeredLabel("←", 15, GREEN, true));
        card.addView(footer);
        setClick(card, new Runnable() {
            @Override public void run() { openLesson(lesson.id); }
        });
        return card;
    }

    private View buildGlossary() {
        LinearLayout column = pageColumn();
        addHomeHeader(column);
        addSpace(column, 22);
        addPageTitle(column, "دانشنامهٔ بلوک‌ها", "واژه‌های پرکاربرد بازی را سریع پیدا کن.");
        addSpace(column, 15);

        final EditText search = new EditText(this);
        search.setSingleLine(true);
        search.setTextSize(14);
        search.setTextColor(TEXT);
        search.setHintTextColor(MUTED);
        search.setHint("جست‌وجو؛ مثلاً ردستون یا کلنگ");
        search.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        search.setTextDirection(View.TEXT_DIRECTION_FIRST_STRONG);
        search.setImeOptions(EditorInfo.IME_ACTION_SEARCH);
        search.setPadding(dp(15), dp(11), dp(15), dp(11));
        search.setBackground(rounded(PANEL, 15, BORDER));
        column.addView(search, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, dp(52)));
        addSpace(column, 14);

        final LinearLayout results = new LinearLayout(this);
        results.setOrientation(LinearLayout.VERTICAL);
        column.addView(results, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        populateGlossary(results, "");
        search.addTextChangedListener(new TextWatcher() {
            @Override public void beforeTextChanged(CharSequence s, int start, int count, int after) { }
            @Override public void onTextChanged(CharSequence s, int start, int before, int count) {
                populateGlossary(results, s.toString());
            }
            @Override public void afterTextChanged(Editable s) { }
        });
        return wrapPage(column);
    }

    private void populateGlossary(LinearLayout results, String query) {
        results.removeAllViews();
        String normalized = query.toLowerCase(Locale.getDefault()).trim();
        int matches = 0;
        for (int i = 0; i < glossary.length; i++) {
            String[] term = glossary[i];
            String haystack = (term[0] + " " + term[1] + " " + term[2]).toLowerCase(Locale.getDefault());
            if (normalized.length() == 0 || haystack.contains(normalized)) {
                LinearLayout card = new LinearLayout(this);
                card.setOrientation(LinearLayout.VERTICAL);
                card.setPadding(dp(14), dp(13), dp(14), dp(13));
                card.setBackground(rounded(PANEL, 16, BORDER));
                LinearLayout top = horizontal();
                top.setGravity(Gravity.CENTER_VERTICAL);
                TextView title = label(term[0], 15, TEXT, true);
                top.addView(title, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
                TextView tag = centeredLabel(term[2], 10, GREEN, true);
                tag.setPadding(dp(9), dp(5), dp(9), dp(5));
                tag.setBackground(rounded(GREEN_DARK, 16, 0));
                top.addView(tag);
                card.addView(top);
                addSpace(card, 7);
                TextView desc = label(term[1], 12, MUTED, false);
                card.addView(desc);
                results.addView(card, new LinearLayout.LayoutParams(
                        ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
                addSpace(results, 9);
                matches++;
            }
        }
        if (matches == 0) {
            TextView empty = centeredLabel("چیزی پیدا نشد؛ واژهٔ دیگری امتحان کن.", 13, MUTED, false);
            empty.setPadding(dp(12), dp(18), dp(12), dp(18));
            results.addView(empty);
        }
    }

    private View buildProgress() {
        LinearLayout column = pageColumn();
        addHomeHeader(column);
        addSpace(column, 22);
        addPageTitle(column, "دفتر ماجراجویی", "درس‌هایت را مرور کن و نشان‌های تازه بگیر.");
        addSpace(column, 16);

        LinearLayout summary = new LinearLayout(this);
        summary.setOrientation(LinearLayout.VERTICAL);
        summary.setPadding(dp(18), dp(18), dp(18), dp(18));
        summary.setBackground(rounded(PANEL, 21, BORDER));
        LinearLayout summaryTop = horizontal();
        summaryTop.setGravity(Gravity.CENTER_VERTICAL);
        LinearLayout summaryText = new LinearLayout(this);
        summaryText.setOrientation(LinearLayout.VERTICAL);
        summaryText.addView(label(completed.size() == 0 ? "ماجراجوی تازه‌کار" : "ماجراجوی در مسیر", 18, TEXT, true));
        summaryText.addView(label("هر درس یک مهارت تازه؛ ادامه بده!", 12, MUTED, false));
        summaryTop.addView(summaryText, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        TextView level = centeredLabel(completed.size() < 3 ? "مرحله ۱" : completed.size() < 6 ? "مرحله ۲" : "مرحله ۳", 12, GOLD, true);
        level.setPadding(dp(11), dp(8), dp(11), dp(8));
        level.setBackground(rounded(0xFF3C3422, 20, 0));
        summaryTop.addView(level);
        summary.addView(summaryTop);
        addSpace(summary, 16);
        LinearLayout numbers = horizontal();
        addStat(numbers, completed.size() + "/" + lessons.size(), "درس‌ها");
        addStat(numbers, String.valueOf(xp()), "امتیاز XP");
        addStat(numbers, progressPercent() + "%", "پیشرفت");
        summary.addView(numbers, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, dp(65)));
        addSpace(summary, 13);
        summary.addView(new ProgressTrack(this, progressPercent() / 100f),
                new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(9)));
        column.addView(summary, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));

        addSpace(column, 25);
        addSectionHeading(column, "نشان‌های تو", null, null);
        addSpace(column, 11);
        List<View> badges = new ArrayList<View>();
        badges.add(achievementCard("🧭", "کاشف", "اولین درس را تمام کن", completed.size() >= 1));
        badges.add(achievementCard("🧰", "سازنده", "سه درس را تمام کن", completed.size() >= 3));
        badges.add(achievementCard("⛏️", "معدن‌رو", "پنج درس را تمام کن", completed.size() >= 5));
        badges.add(achievementCard("🐉", "قهرمان اِنْد", "همهٔ هشت درس را تمام کن", completed.size() >= 8));
        addViewGrid(column, badges);

        addSpace(column, 23);
        addSectionHeading(column, "درس‌های کامل‌شده", null, null);
        addSpace(column, 10);
        int doneCount = 0;
        for (int i = 0; i < lessons.size(); i++) {
            if (completed.contains(i)) {
                column.addView(completedRow(lessons.get(i)), new LinearLayout.LayoutParams(
                        ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
                addSpace(column, 8);
                doneCount++;
            }
        }
        if (doneCount == 0) {
            TextView empty = label("هنوز درسی کامل نشده؛ از «نجات از شب اول» شروع کن.", 13, MUTED, false);
            empty.setPadding(dp(3), dp(8), dp(3), dp(8));
            column.addView(empty);
        }
        addSpace(column, 15);
        TextView reset = centeredLabel("پاک‌کردن پیشرفت", 12, RED, true);
        reset.setPadding(dp(15), dp(11), dp(15), dp(11));
        reset.setBackground(rounded(0xFF342321, 13, 0));
        column.addView(reset, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        setClick(reset, new Runnable() {
            @Override public void run() { confirmReset(); }
        });
        addSpace(column, 10);
        column.addView(centeredLabel("راهنمای آموزشی غیررسمی؛ بدون وابستگی به اینترنت.", 10, MUTED, false));
        return wrapPage(column);
    }

    private void addViewGrid(LinearLayout column, List<View> views) {
        for (int i = 0; i < views.size(); i += 2) {
            LinearLayout row = horizontal();
            row.addView(views.get(i), new LinearLayout.LayoutParams(0,
                    ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
            row.addView(new View(this), new LinearLayout.LayoutParams(dp(10), 1));
            if (i + 1 < views.size()) {
                row.addView(views.get(i + 1), new LinearLayout.LayoutParams(0,
                        ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
            } else {
                row.addView(new View(this), new LinearLayout.LayoutParams(0, 1, 1f));
            }
            column.addView(row, new LinearLayout.LayoutParams(
                    ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
            addSpace(column, 10);
        }
    }

    private View achievementCard(String emoji, String title, String description, boolean unlocked) {
        LinearLayout card = new LinearLayout(this);
        card.setOrientation(LinearLayout.VERTICAL);
        card.setPadding(dp(13), dp(13), dp(13), dp(13));
        card.setBackground(rounded(unlocked ? 0xFF273A2A : PANEL, 17,
                unlocked ? 0xFF537548 : BORDER));
        TextView icon = centeredLabel(emoji, 25, TEXT, false);
        icon.setGravity(Gravity.CENTER);
        card.addView(icon, new LinearLayout.LayoutParams(dp(42), dp(42)));
        addSpace(card, 9);
        card.addView(label((unlocked ? "✓  " : "🔒  ") + title, 14,
                unlocked ? GREEN : MUTED, true));
        addSpace(card, 4);
        TextView note = label(description, 10, MUTED, false);
        note.setMaxLines(2);
        card.addView(note);
        return card;
    }

    private View completedRow(final Lesson lesson) {
        LinearLayout row = horizontal();
        row.setGravity(Gravity.CENTER_VERTICAL);
        row.setPadding(dp(13), dp(11), dp(13), dp(11));
        row.setBackground(rounded(PANEL, 15, BORDER));
        TextView icon = centeredLabel(lesson.emoji, 20, TEXT, false);
        row.addView(icon, new LinearLayout.LayoutParams(dp(36), dp(36)));
        LinearLayout copy = new LinearLayout(this);
        copy.setOrientation(LinearLayout.VERTICAL);
        copy.setPadding(0, 0, dp(9), 0);
        copy.addView(label(lesson.title, 13, TEXT, true));
        copy.addView(label(lesson.category + "  ·  " + lesson.duration, 10, MUTED, false));
        row.addView(copy, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        row.addView(centeredLabel("✓", 18, GREEN, true));
        setClick(row, new Runnable() {
            @Override public void run() { openLesson(lesson.id); }
        });
        return row;
    }

    private View buildLessonDetail() {
        final Lesson lesson = lessons.get(selectedLesson);
        LinearLayout column = pageColumn();
        addBackTitle(column, "درس آموزشی", false);
        addSpace(column, 15);

        LinearLayout cover = horizontal();
        cover.setGravity(Gravity.CENTER_VERTICAL);
        cover.setPadding(dp(16), dp(16), dp(16), dp(16));
        cover.setBackground(rounded(PANEL, 21, BORDER));
        TextView icon = centeredLabel(lesson.emoji, 34, TEXT, false);
        icon.setBackground(rounded(lesson.tint, 18, 0));
        cover.addView(icon, new LinearLayout.LayoutParams(dp(64), dp(64)));
        LinearLayout titles = new LinearLayout(this);
        titles.setOrientation(LinearLayout.VERTICAL);
        titles.setPadding(0, 0, dp(13), 0);
        TextView category = label(lesson.category + "  ·  " + lesson.duration, 11, GREEN, true);
        titles.addView(category);
        addSpace(titles, 5);
        TextView title = label(lesson.title, 20, TEXT, true);
        title.setMaxLines(2);
        titles.addView(title);
        cover.addView(titles, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        column.addView(cover, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));

        addSpace(column, 17);
        addSectionHeading(column, "مأموریت این درس", null, null);
        addSpace(column, 7);
        TextView intro = label(lesson.intro, 14, 0xFFD2DDD1, false);
        intro.setLineSpacing(dp(5), 1.0f);
        column.addView(intro);

        addSpace(column, 21);
        addSectionHeading(column, "قدم‌به‌قدم", null, null);
        addSpace(column, 10);
        for (int i = 0; i < lesson.steps.length; i++) {
            column.addView(stepCard(i + 1, lesson.steps[i]), new LinearLayout.LayoutParams(
                    ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
            addSpace(column, 9);
        }

        addSpace(column, 5);
        LinearLayout tip = horizontal();
        tip.setGravity(Gravity.TOP);
        tip.setPadding(dp(14), dp(14), dp(14), dp(14));
        tip.setBackground(rounded(0xFF302B1D, 16, 0xFF55472A));
        TextView star = centeredLabel("✦", 20, GOLD, true);
        tip.addView(star, new LinearLayout.LayoutParams(dp(32), dp(32)));
        LinearLayout tipText = new LinearLayout(this);
        tipText.setOrientation(LinearLayout.VERTICAL);
        tipText.setPadding(0, 0, dp(8), 0);
        tipText.addView(label("نکتهٔ حرفه‌ای", 12, GOLD, true));
        addSpace(tipText, 4);
        TextView tipBody = label(lesson.tip, 12, 0xFFE0D9C7, false);
        tipBody.setLineSpacing(dp(3), 1.0f);
        tipText.addView(tipBody);
        tip.addView(tipText, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        column.addView(tip, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));

        addSpace(column, 22);
        LinearLayout quizCard = new LinearLayout(this);
        quizCard.setOrientation(LinearLayout.VERTICAL);
        quizCard.setPadding(dp(16), dp(16), dp(16), dp(16));
        quizCard.setBackground(rounded(PANEL_LIGHT, 20, BORDER));
        LinearLayout quizTitle = horizontal();
        quizTitle.setGravity(Gravity.CENTER_VERTICAL);
        TextView quizName = label("آماده‌ای خودت را بسنجی؟", 15, TEXT, true);
        quizTitle.addView(quizName, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        quizTitle.addView(centeredLabel("❔", 20, GOLD, true));
        quizCard.addView(quizTitle);
        addSpace(quizCard, 6);
        quizCard.addView(label("یک پرسش کوتاه  ·  پاسخ درست = ۵۰ XP", 11, MUTED, false));
        addSpace(quizCard, 13);
        TextView quizButton = centeredLabel(completed.contains(lesson.id) ? "مرور آزمون  ←" : "شروع آزمون  ←", 13,
                Color.rgb(22, 37, 24), true);
        quizButton.setPadding(dp(14), dp(11), dp(14), dp(11));
        quizButton.setBackground(rounded(GREEN, 13, 0));
        quizCard.addView(quizButton, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        setClick(quizCard, new Runnable() {
            @Override public void run() { openQuiz(); }
        });
        column.addView(quizCard, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
        addSpace(column, 10);
        if (completed.contains(lesson.id)) {
            TextView done = centeredLabel("این درس در دفتر ماجراجویی ثبت شده است ✓", 11, GREEN, true);
            column.addView(done);
        }
        return wrapPage(column);
    }

    private View stepCard(int number, String content) {
        LinearLayout row = horizontal();
        row.setGravity(Gravity.TOP);
        row.setPadding(dp(12), dp(12), dp(12), dp(12));
        row.setBackground(rounded(PANEL, 16, BORDER));
        TextView numberView = centeredLabel(persianNumber(number), 13, Color.rgb(20, 35, 22), true);
        numberView.setBackground(rounded(GREEN, 13, 0));
        row.addView(numberView, new LinearLayout.LayoutParams(dp(34), dp(34)));
        TextView description = label(content, 13, 0xFFE0E8DE, false);
        description.setPadding(0, 0, dp(10), 0);
        description.setLineSpacing(dp(3), 1.0f);
        row.addView(description, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        return row;
    }

    private View buildQuiz() {
        final Lesson lesson = lessons.get(selectedLesson);
        LinearLayout column = pageColumn();
        addBackTitle(column, "آزمون کوتاه", true);
        addSpace(column, 18);

        LinearLayout progressCard = new LinearLayout(this);
        progressCard.setOrientation(LinearLayout.VERTICAL);
        progressCard.setPadding(dp(15), dp(14), dp(15), dp(14));
        progressCard.setBackground(rounded(PANEL, 17, BORDER));
        LinearLayout progressHeading = horizontal();
        progressHeading.addView(label(lesson.title, 13, TEXT, true),
                new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        progressHeading.addView(centeredLabel("۱ پرسش", 11, GOLD, true));
        progressCard.addView(progressHeading);
        addSpace(progressCard, 10);
        progressCard.addView(new ProgressTrack(this, quizSubmitted ? 1f : 0.45f),
                new LinearLayout.LayoutParams(ViewGroup.LayoutParams.MATCH_PARENT, dp(7)));
        column.addView(progressCard, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));

        addSpace(column, 17);
        LinearLayout questionCard = new LinearLayout(this);
        questionCard.setOrientation(LinearLayout.VERTICAL);
        questionCard.setPadding(dp(17), dp(18), dp(17), dp(18));
        questionCard.setBackground(rounded(PANEL_LIGHT, 20, BORDER));
        questionCard.addView(label("پرسش ماجراجو", 11, GREEN, true));
        addSpace(questionCard, 9);
        TextView question = label(lesson.question, 18, TEXT, true);
        question.setLineSpacing(dp(3), 1.0f);
        questionCard.addView(question);
        column.addView(questionCard, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));

        addSpace(column, 15);
        for (int i = 0; i < lesson.options.length; i++) {
            final int optionIndex = i;
            boolean isSelected = selectedAnswer == i;
            boolean isCorrect = i == lesson.answer;
            int fill = isSelected ? 0xFF293A2D : PANEL;
            int stroke = isSelected ? GREEN : BORDER;
            if (quizSubmitted && isCorrect) { fill = 0xFF243B29; stroke = 0xFF80BD58; }
            if (quizSubmitted && isSelected && !isCorrect) { fill = 0xFF3A2926; stroke = RED; }

            LinearLayout option = horizontal();
            option.setGravity(Gravity.CENTER_VERTICAL);
            option.setPadding(dp(13), dp(13), dp(13), dp(13));
            option.setBackground(rounded(fill, 15, stroke));
            TextView marker = centeredLabel(persianNumber(i + 1), 12,
                    quizSubmitted && isCorrect ? GREEN : (isSelected ? GREEN : MUTED), true);
            marker.setBackground(rounded(0xFF344638, 20, 0));
            option.addView(marker, new LinearLayout.LayoutParams(dp(34), dp(34)));
            TextView optionText = label(lesson.options[i], 13, TEXT, false);
            optionText.setPadding(0, 0, dp(10), 0);
            option.addView(optionText, new LinearLayout.LayoutParams(0,
                    ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
            if (quizSubmitted && isCorrect) option.addView(centeredLabel("✓", 18, GREEN, true));
            column.addView(option, new LinearLayout.LayoutParams(
                    ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
            addSpace(column, 9);
            if (!quizSubmitted) {
                setClick(option, new Runnable() {
                    @Override public void run() { selectedAnswer = optionIndex; render(); }
                });
            }
        }

        if (quizSubmitted) {
            boolean correct = selectedAnswer == lesson.answer;
            LinearLayout feedback = new LinearLayout(this);
            feedback.setOrientation(LinearLayout.VERTICAL);
            feedback.setPadding(dp(14), dp(14), dp(14), dp(14));
            feedback.setBackground(rounded(correct ? 0xFF243B29 : 0xFF382825, 16,
                    correct ? 0xFF537548 : 0xFF65423B));
            feedback.addView(label(correct ? "درست گفتی!  " + (justEarnedXp ? "+۵۰ XP" : "عالیه") : "این بار نشد؛ دوباره امتحان کن.",
                    14, correct ? GREEN : RED, true));
            addSpace(feedback, 6);
            TextView why = label(lesson.explanation, 12, 0xFFDCE5DB, false);
            why.setLineSpacing(dp(3), 1.0f);
            feedback.addView(why);
            column.addView(feedback, new LinearLayout.LayoutParams(
                    ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
            addSpace(column, 13);

            TextView next = centeredLabel(correct ? "بازگشت به درس  ←" : "تلاش دوباره  ↻", 13,
                    Color.rgb(22, 37, 24), true);
            next.setPadding(dp(15), dp(12), dp(15), dp(12));
            next.setBackground(rounded(GREEN, 14, 0));
            column.addView(next, new LinearLayout.LayoutParams(
                    ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
            setClick(next, new Runnable() {
                @Override public void run() {
                    if (selectedAnswer == lesson.answer) {
                        route = "detail";
                    } else {
                        selectedAnswer = -1;
                        quizSubmitted = false;
                        justEarnedXp = false;
                    }
                    render();
                }
            });
        } else {
            TextView submit = centeredLabel("بررسی پاسخ  ✓", 13, Color.rgb(22, 37, 24), true);
            submit.setPadding(dp(15), dp(12), dp(15), dp(12));
            submit.setBackground(rounded(GREEN, 14, 0));
            column.addView(submit, new LinearLayout.LayoutParams(
                    ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
            setClick(submit, new Runnable() {
                @Override public void run() {
                    if (selectedAnswer < 0) {
                        Toast.makeText(MainActivity.this, "اول یکی از پاسخ‌ها را انتخاب کن.", Toast.LENGTH_SHORT).show();
                        return;
                    }
                    quizSubmitted = true;
                    justEarnedXp = false;
                    if (selectedAnswer == lesson.answer && !completed.contains(lesson.id)) {
                        completed.add(lesson.id);
                        justEarnedXp = true;
                        saveProgress();
                    }
                    render();
                }
            });
        }
        addSpace(column, 12);
        return wrapPage(column);
    }

    private void addBackTitle(LinearLayout column, String title, final boolean fromQuiz) {
        LinearLayout row = horizontal();
        row.setGravity(Gravity.CENTER_VERTICAL);
        TextView back = centeredLabel("←", 22, TEXT, true);
        back.setBackground(rounded(PANEL_LIGHT, 15, BORDER));
        row.addView(back, new LinearLayout.LayoutParams(dp(44), dp(44)));
        setClick(back, new Runnable() {
            @Override public void run() {
                if (fromQuiz) route = "detail";
                else route = returnRoute;
                render();
            }
        });
        TextView heading = label(title, 19, TEXT, true);
        heading.setPadding(0, 0, dp(12), 0);
        row.addView(heading, new LinearLayout.LayoutParams(0,
                ViewGroup.LayoutParams.MATCH_PARENT, 1f));
        column.addView(row, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, dp(48)));
    }

    private void addPageTitle(LinearLayout column, String title, String subtitle) {
        TextView heading = label(title, 23, TEXT, true);
        column.addView(heading);
        addSpace(column, 5);
        TextView sub = label(subtitle, 12, MUTED, false);
        sub.setLineSpacing(dp(2), 1.0f);
        column.addView(sub);
    }

    private void addSectionHeading(LinearLayout column, String title, String action, final Runnable callback) {
        LinearLayout row = horizontal();
        row.setGravity(Gravity.CENTER_VERTICAL);
        TextView heading = label(title, 17, TEXT, true);
        row.addView(heading, new LinearLayout.LayoutParams(0, ViewGroup.LayoutParams.WRAP_CONTENT, 1f));
        if (action != null) {
            TextView actionView = centeredLabel(action + "  ←", 11, GREEN, true);
            row.addView(actionView, new LinearLayout.LayoutParams(
                    ViewGroup.LayoutParams.WRAP_CONTENT, ViewGroup.LayoutParams.WRAP_CONTENT));
            if (callback != null) setClick(actionView, callback);
        }
        column.addView(row, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, ViewGroup.LayoutParams.WRAP_CONTENT));
    }

    private void refreshBottomBar() {
        bottomBar.removeAllViews();
        bottomBar.setBackgroundColor(BG);
        View divider = new View(this);
        divider.setBackgroundColor(0xFF28352B);
        bottomBar.addView(divider, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, dp(1)));
        LinearLayout nav = horizontal();
        nav.setGravity(Gravity.CENTER_VERTICAL);
        nav.setPadding(dp(7), dp(5), dp(7), dp(5));
        String selected = ("detail".equals(route) || "quiz".equals(route)) ? returnRoute : route;
        nav.addView(navItem("⌂", "خانه", "home", selected), new LinearLayout.LayoutParams(0,
                ViewGroup.LayoutParams.MATCH_PARENT, 1f));
        nav.addView(navItem("▦", "درس‌ها", "lessons", selected), new LinearLayout.LayoutParams(0,
                ViewGroup.LayoutParams.MATCH_PARENT, 1f));
        nav.addView(navItem("⌕", "دانشنامه", "glossary", selected), new LinearLayout.LayoutParams(0,
                ViewGroup.LayoutParams.MATCH_PARENT, 1f));
        nav.addView(navItem("★", "دفتر من", "progress", selected), new LinearLayout.LayoutParams(0,
                ViewGroup.LayoutParams.MATCH_PARENT, 1f));
        bottomBar.addView(nav, new LinearLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT, 0, 1f));
    }

    private View navItem(String icon, String title, final String target, String selected) {
        boolean active = target.equals(selected);
        LinearLayout item = new LinearLayout(this);
        item.setOrientation(LinearLayout.VERTICAL);
        item.setGravity(Gravity.CENTER);
        item.setPadding(dp(2), dp(4), dp(2), dp(3));
        item.setBackground(rounded(active ? 0xFF26392B : Color.TRANSPARENT, 14, 0));
        TextView symbol = centeredLabel(icon, 19, active ? GREEN : MUTED, true);
        TextView caption = centeredLabel(title, 10, active ? GREEN : MUTED, active);
        item.addView(symbol);
        item.addView(caption);
        setClick(item, new Runnable() {
            @Override public void run() {
                route = target;
                render();
            }
        });
        return item;
    }

    private View buildButton(String text, final Runnable action, int background, int foreground) {
        TextView button = centeredLabel(text, 13, foreground, true);
        button.setPadding(dp(15), dp(11), dp(15), dp(11));
        button.setBackground(rounded(background, 14, 0));
        setClick(button, action);
        return button;
    }

    private void confirmReset() {
        AlertDialog dialog = new AlertDialog.Builder(this)
                .setTitle("پاک‌کردن پیشرفت؟")
                .setMessage("نشان‌ها و امتیازهای این دستگاه پاک می‌شوند. این کار برگشت‌پذیر نیست.")
                .setNegativeButton("بی‌خیال", null)
                .setPositiveButton("پاک کن", new android.content.DialogInterface.OnClickListener() {
                    @Override public void onClick(android.content.DialogInterface dialog, int which) {
                        completed.clear();
                        saveProgress();
                        route = "progress";
                        render();
                    }
                })
                .create();
        dialog.show();
    }

    private void openLesson(int id) {
        if (!"detail".equals(route) && !"quiz".equals(route)) returnRoute = route;
        selectedLesson = id;
        route = "detail";
        render();
    }

    private void openQuiz() {
        selectedAnswer = -1;
        quizSubmitted = false;
        justEarnedXp = false;
        route = "quiz";
        render();
    }

    private int nextLesson() {
        for (int i = 0; i < lessons.size(); i++) {
            if (!completed.contains(i)) return i;
        }
        return 0;
    }

    private int progressPercent() {
        if (lessons.size() == 0) return 0;
        return (completed.size() * 100) / lessons.size();
    }

    private int xp() {
        return completed.size() * 50;
    }

    private String persianNumber(int number) {
        String latin = String.valueOf(number);
        StringBuilder result = new StringBuilder();
        for (int i = 0; i < latin.length(); i++) {
            char ch = latin.charAt(i);
            result.append((char) ('\u06F0' + (ch - '0')));
        }
        return result.toString();
    }

    private LinearLayout horizontal() {
        LinearLayout row = new LinearLayout(this);
        row.setOrientation(LinearLayout.HORIZONTAL);
        row.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        return row;
    }

    private TextView label(String value, float size, int color, boolean bold) {
        TextView text = new TextView(this);
        text.setText(value);
        text.setTextSize(size);
        text.setTextColor(color);
        text.setTypeface(Typeface.create("sans-serif", bold ? Typeface.BOLD : Typeface.NORMAL));
        text.setGravity(Gravity.RIGHT | Gravity.CENTER_VERTICAL);
        text.setTextDirection(View.TEXT_DIRECTION_FIRST_STRONG);
        text.setLayoutDirection(View.LAYOUT_DIRECTION_RTL);
        text.setLineSpacing(dp(2), 1.0f);
        return text;
    }

    private TextView centeredLabel(String value, float size, int color, boolean bold) {
        TextView text = label(value, size, color, bold);
        text.setGravity(Gravity.CENTER);
        return text;
    }

    private void addSpace(LinearLayout parent, int height) {
        View space = new View(this);
        parent.addView(space, new LinearLayout.LayoutParams(1, dp(height)));
    }

    private int dp(float value) {
        return (int) (getResources().getDisplayMetrics().density * value + 0.5f);
    }

    private GradientDrawable rounded(int color, int radiusDp, int strokeColor) {
        GradientDrawable shape = new GradientDrawable();
        shape.setColor(color);
        shape.setCornerRadius(dp(radiusDp));
        if (strokeColor != 0) shape.setStroke(dp(1), strokeColor);
        return shape;
    }

    private void setClick(View view, final Runnable action) {
        view.setClickable(true);
        view.setFocusable(true);
        view.setOnClickListener(new View.OnClickListener() {
            @Override public void onClick(View v) { action.run(); }
        });
    }

    @Override
    public void onBackPressed() {
        if ("quiz".equals(route)) {
            route = "detail";
            render();
        } else if ("detail".equals(route)) {
            route = returnRoute;
            render();
        } else if (!"home".equals(route)) {
            route = "home";
            render();
        } else {
            super.onBackPressed();
        }
    }

    private static class Lesson {
        final int id;
        final String title;
        final String category;
        final String emoji;
        final String duration;
        final String intro;
        final String[] steps;
        final String tip;
        final String question;
        final String[] options;
        final int answer;
        final String explanation;
        final int tint;

        Lesson(int id, String title, String category, String emoji, String duration,
               String intro, String[] steps, String tip, String question,
               String[] options, int answer, String explanation) {
            this.id = id;
            this.title = title;
            this.category = category;
            this.emoji = emoji;
            this.duration = duration;
            this.intro = intro;
            this.steps = steps;
            this.tip = tip;
            this.question = question;
            this.options = options;
            this.answer = answer;
            this.explanation = explanation;
            this.tint = tintFor(id);
        }

        private int tintFor(int id) {
            int[] colors = new int[]{0xFF36432D, 0xFF3B3325, 0xFF343B2C, 0xFF343E27,
                    0xFF343238, 0xFF2D3844, 0xFF3B302E, 0xFF40302A};
            return colors[id % colors.length];
        }
    }

    private static class ProgressTrack extends View {
        private final Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG);
        private final float progress;

        ProgressTrack(Context context, float progress) {
            super(context);
            this.progress = Math.max(0f, Math.min(1f, progress));
        }

        @Override protected void onDraw(Canvas canvas) {
            super.onDraw(canvas);
            float height = getHeight();
            float radius = height / 2f;
            paint.setColor(0xFF344236);
            canvas.drawRoundRect(0, 0, getWidth(), height, radius, radius, paint);
            if (progress > 0f) {
                float width = Math.max(height, getWidth() * progress);
                paint.setColor(GREEN);
                canvas.drawRoundRect(getWidth() - width, 0, getWidth(), height, radius, radius, paint);
            }
        }
    }

    private static class CubeLogo extends View {
        private final Paint paint = new Paint(Paint.ANTI_ALIAS_FLAG);
        CubeLogo(Context context) { super(context); }

        @Override protected void onDraw(Canvas canvas) {
            super.onDraw(canvas);
            canvas.save();
            canvas.scale(getWidth() / 48f, getHeight() / 48f);
            paint.setColor(0xFF1C3021);
            canvas.drawRoundRect(0, 0, 48, 48, 14, 14, paint);
            Path top = new Path();
            top.moveTo(8, 17); top.lineTo(24, 8); top.lineTo(40, 17); top.lineTo(24, 26); top.close();
            paint.setColor(0xFF8FDB58); canvas.drawPath(top, paint);
            Path left = new Path();
            left.moveTo(8, 17); left.lineTo(24, 26); left.lineTo(24, 42); left.lineTo(8, 33); left.close();
            paint.setColor(0xFF795232); canvas.drawPath(left, paint);
            Path right = new Path();
            right.moveTo(24, 26); right.lineTo(40, 17); right.lineTo(40, 33); right.lineTo(24, 42); right.close();
            paint.setColor(0xFF5B3E2B); canvas.drawPath(right, paint);
            paint.setColor(0xFF55943C);
            canvas.drawRect(9, 18, 23, 21, paint);
            paint.setColor(0xFF72BA49);
            canvas.drawRect(25, 26, 39, 29, paint);
            paint.setColor(0xFF9D7045);
            canvas.drawRect(12, 25, 16, 28, paint);
            canvas.drawRect(29, 33, 33, 36, paint);
            canvas.restore();
        }
    }

    private static class PixelSceneView extends View {
        private final Paint paint = new Paint();

        PixelSceneView(Context context) {
            super(context);
            paint.setAntiAlias(false);
        }

        private void rect(Canvas canvas, float left, float top, float right, float bottom, int color) {
            paint.setShader(null);
            paint.setColor(color);
            canvas.drawRect(left, top, right, bottom, paint);
        }

        private void triangle(Canvas canvas, float x1, float y1, float x2, float y2,
                              float x3, float y3, int color) {
            Path path = new Path();
            path.moveTo(x1, y1); path.lineTo(x2, y2); path.lineTo(x3, y3); path.close();
            paint.setShader(null);
            paint.setColor(color);
            canvas.drawPath(path, paint);
        }

        private void tree(Canvas canvas, float x, float ground, float scale) {
            rect(canvas, x + scale, ground - 35 * scale, x + 2 * scale, ground, 0xFF60452E);
            rect(canvas, x - 2 * scale, ground - 47 * scale, x + 5 * scale, ground - 31 * scale, 0xFF426D3C);
            rect(canvas, x - 7 * scale, ground - 41 * scale, x + 1 * scale, ground - 30 * scale, 0xFF528447);
            rect(canvas, x + 2 * scale, ground - 42 * scale, x + 9 * scale, ground - 32 * scale, 0xFF5A9149);
            rect(canvas, x - 2 * scale, ground - 35 * scale, x + 7 * scale, ground - 26 * scale, 0xFF47763D);
        }

        @Override protected void onDraw(Canvas canvas) {
            super.onDraw(canvas);
            float sx = getWidth() / 360f;
            float sy = getHeight() / 224f;
            canvas.save();
            canvas.scale(sx, sy);

            paint.setShader(new LinearGradient(0, 0, 0, 224,
                    0xFF4E806A, 0xFFB0B774, Shader.TileMode.CLAMP));
            canvas.drawRect(0, 0, 360, 224, paint);
            paint.setShader(null);

            // Blocky sun and layered square clouds.
            rect(canvas, 274, 28, 309, 63, 0xFFFFD879);
            rect(canvas, 44, 37, 92, 45, 0xFFD5E2BA);
            rect(canvas, 56, 29, 83, 53, 0xFFD5E2BA);
            rect(canvas, 108, 61, 150, 68, 0xFFC8D9B1);
            rect(canvas, 120, 53, 142, 74, 0xFFC8D9B1);

            triangle(canvas, -10, 164, 83, 76, 181, 164, 0xFF667F5C);
            triangle(canvas, 96, 163, 204, 66, 306, 163, 0xFF718A61);
            triangle(canvas, 215, 164, 302, 91, 386, 164, 0xFF526F50);
            triangle(canvas, 45, 163, 108, 103, 171, 163, 0xFF84956B);
            rect(canvas, 0, 154, 360, 224, 0xFF644A32);
            rect(canvas, 0, 148, 360, 164, 0xFF5D8A45);
            rect(canvas, 0, 164, 360, 171, 0xFF745238);

            // Little square patches give the ground a crafted, voxel texture.
            for (int x = 0; x < 360; x += 24) {
                if ((x / 24) % 2 == 0) rect(canvas, x, 171, x + 24, 196, 0xFF684B32);
                else rect(canvas, x, 171, x + 24, 196, 0xFF725238);
                if ((x / 24) % 3 == 0) rect(canvas, x + 4, 184, x + 9, 189, 0xFF82613E);
            }
            rect(canvas, 0, 196, 360, 224, 0xFF533E2C);
            tree(canvas, 28, 151, 1.1f);
            tree(canvas, 91, 151, 0.8f);
            tree(canvas, 324, 151, 1.05f);

            // A tiny original explorer silhouette, made from simple colored squares.
            rect(canvas, 177, 115, 190, 128, 0xFFD5A477);
            rect(canvas, 175, 128, 192, 145, 0xFF3B6580);
            rect(canvas, 176, 143, 182, 153, 0xFF354137);
            rect(canvas, 185, 143, 191, 153, 0xFF354137);
            rect(canvas, 174, 117, 180, 120, 0xFF593E2D);
            rect(canvas, 187, 117, 192, 120, 0xFF593E2D);
            rect(canvas, 182, 119, 184, 121, 0xFF252C22);

            // Small glowing trail markers.
            rect(canvas, 228, 136, 232, 148, 0xFF77543A);
            rect(canvas, 224, 130, 236, 138, 0xFFFFD66D);
            rect(canvas, 245, 142, 249, 151, 0xFF77543A);
            rect(canvas, 242, 136, 252, 143, 0xFFFFE394);
            canvas.restore();
        }
    }
}
