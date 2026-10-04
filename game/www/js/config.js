/* =========================================================================
 *  قارچ‌خور — Gharche-Khor (Persian platformer)
 *  config.js — ثابت‌ها، تم‌های بصری، شخصیت‌ها و تعریف مراحل
 * ========================================================================= */

export const GAME_TITLE = 'قارچ‌خور';
export const GAME_SUBTITLE = 'ماجراهای کوکو در ایران';
export const VERSION = '1.0.1';

/* ------------------------------- فیزیک بازی ------------------------------ */
export const TILE = 16;                 // اندازهٔ هر خانه (پیکسل منطقی)
export const GRAVITY = 1500;            // شتاب گرانش px/s²
export const MAX_FALL = 580;
export const WALK_ACCEL = 780;
export const RUN_ACCEL = 1050;
export const MAX_WALK = 118;
export const MAX_RUN = 196;
export const FRICTION = 1350;
export const AIR_ACCEL = 620;
export const JUMP_VEL = -442;
export const JUMP_CUT = 0.42;           // ضریب کوتاه‌شدن پرش با رها کردن دکمه
export const STOMP_BOUNCE = -318;
export const STOMP_BOUNCE_RUN = -408;
export const ENEMY_BOUNCE = -360;
export const COYOTE_TIME = 0.09;        // مهلت پرش بعد از ترک لبه
export const JUMP_BUFFER = 0.11;        // بافر فشردن دکمهٔ پرش
export const DEATH_Y = 260;             // افتادن از این عمق = مرگ
export const CAM_LERP = 7.5;
export const FIXED_DT = 1 / 60;

/* --------------------------------- وضعیت‌ها ------------------------------ */
export const POWER = { SMALL: 0, BIG: 1, FIRE: 2 };
export const STAR_TIME = 11.5;
export const FEATHER_TIME = 16;
export const INVULN_TIME = 1.9;
export const LEVEL_TIME = 300;

/* --------------------------------- شخصیت‌ها ------------------------------ */
export const CHARACTERS = [
  {
    id: 'koko', name: 'کوکو', desc: 'متعادل و همه‌کاره', icon: '🧒',
    speed: 1.0, jump: 1.0, unlock: 0, hatColor: '#d0222c', vestColor: '#2f8f4e',
  },
  {
    id: 'niloofar', name: 'نیلوفر', desc: 'تند و تیز، پرش کوتاه‌تر', icon: '👧',
    speed: 1.12, jump: 0.94, unlock: 900, hatColor: '#8c3fbf', vestColor: '#e0672a',
  },
  {
    id: 'norouz', name: 'استاد نوروز', desc: 'پرش بلند، کمی کندتر', icon: '🧙',
    speed: 0.92, jump: 1.12, unlock: 1800, hatColor: '#0f7f8a', vestColor: '#b8860b',
  },
];

/* -------------------------------- تم‌های بصری ----------------------------- */
/* هر تم: رنگ آسمان، زمین، آجر، تزئینات، موسیقی و افکت‌های محیطی            */
export const THEMES = {
  alley: {
    id: 'alley', name: 'کوچهٔ کاهگلی',
    sky: ['#5fb7ef', '#a9e2fb', '#ffe6b8'],
    sun: '#fff3c4',
    far: '#8ea9c4', mid: '#b98a5e', near: '#e0b177',
    groundTop: '#6fbf4a', ground: '#c98b52', groundDark: '#8a5a2b',
    brick: '#d9a066', brickDark: '#a3743f',
    block: '#f0c04a', blockDark: '#b8862b',
    stone: '#b9b3a6', stoneDark: '#7d786d',
    pipe: '#2f9e4f', pipeDark: '#1b6b34',
    water: '#3aa7e0', waterDark: '#1f6f9e',
    accent: '#2bb3c0', veg: '#4f9e46', motif: 'kashi', night: false, music: 'shur',
  },
  bazaar: {
    id: 'bazaar', name: 'بازار بزرگ',
    sky: ['#a86b3c', '#e0a86a', '#ffe0ab'],
    sun: '#ffd98a',
    far: '#8a5a3a', mid: '#c07a45', near: '#e3a869',
    groundTop: '#c8905a', ground: '#b07a48', groundDark: '#7a4f2a',
    brick: '#d9a066', brickDark: '#9c6a34',
    block: '#f0c04a', blockDark: '#b8862b',
    stone: '#c2b39a', stoneDark: '#8a7c66',
    pipe: '#8a5cd0', pipeDark: '#5c3a92',
    water: '#3aa7e0', waterDark: '#1f6f9e',
    accent: '#d0453a', veg: '#3f8a4a', motif: 'bazaar', night: false, music: 'mahur',
  },
  garden: {
    id: 'garden', name: 'گلستان ارم',
    sky: ['#4fb3e8', '#b7e9ff', '#ffe9f2'],
    sun: '#fff6cc',
    far: '#7fb2a0', mid: '#4f9e6a', near: '#87c46a',
    groundTop: '#5fbf55', ground: '#8a6b3f', groundDark: '#5c4526',
    brick: '#e28fa8', brickDark: '#a95e75',
    block: '#ffd45e', blockDark: '#c69a2a',
    stone: '#cfc6b8', stoneDark: '#948a7a',
    pipe: '#37a35a', pipeDark: '#1f6b39',
    water: '#4fc3e8', waterDark: '#2a86ad',
    accent: '#ec6aa0', veg: '#2f9e55', motif: 'flower', night: false, music: 'shur',
  },
  desert: {
    id: 'desert', name: 'کاروانسرای کویر',
    sky: ['#f0a95a', '#ffd79a', '#fff0cf'],
    sun: '#fff0b0',
    far: '#c98f5a', mid: '#e0ac6e', near: '#f2c98a',
    groundTop: '#e8c283', ground: '#d8a95e', groundDark: '#9a7233',
    brick: '#e0b271', brickDark: '#a87f42',
    block: '#f5cf6a', blockDark: '#bb8f2f',
    stone: '#d8c9a8', stoneDark: '#9c8a68',
    pipe: '#7a6cc0', pipeDark: '#4f4488',
    water: '#39b0d8', waterDark: '#1d7ba0',
    accent: '#c96a2a', veg: '#7aa03a', motif: 'star', night: false, music: 'mahur',
  },
  qanat: {
    id: 'qanat', name: 'قنات زیرزمینی',
    sky: ['#0a1420', '#132436', '#1d3348'],
    sun: null,
    far: '#16283a', mid: '#1f3a52', near: '#2a4a66',
    groundTop: '#5f6f6a', ground: '#4a5854', groundDark: '#2f3a38',
    brick: '#6a7a72', brickDark: '#42504b',
    block: '#e0b23c', blockDark: '#a37a1d',
    stone: '#6d7a80', stoneDark: '#454f54',
    pipe: '#3f9e8a', pipeDark: '#256b5c',
    water: '#2f8fd0', waterDark: '#155a8a',
    accent: '#5fd0c0', veg: '#2f6a52', motif: 'qanat', night: true, music: 'homayoun',
  },
  persepolis: {
    id: 'persepolis', name: 'تخت جمشید',
    sky: ['#7a6bd8', '#c79ad8', '#ffd0a0'],
    sun: '#ffe6a8',
    far: '#8a7a9a', mid: '#b0a89a', near: '#d6cbb4',
    groundTop: '#cbbfa4', ground: '#b3a688', groundDark: '#7e745c',
    brick: '#d8ccb0', brickDark: '#9a8f74',
    block: '#ffc75e', blockDark: '#c2902a',
    stone: '#ded3ba', stoneDark: '#a1967c',
    pipe: '#9a5fb0', pipeDark: '#663a78',
    water: '#46b6dc', waterDark: '#25789c',
    accent: '#e0a04a', veg: '#5f8a4a', motif: 'homa', night: false, music: 'homayoun',
  },
  tehran: {
    id: 'tehran', name: 'بام‌های تهران',
    sky: ['#101a3a', '#28386e', '#6a4f8a'],
    sun: '#f5e9b8',
    far: '#1b2550', mid: '#2a3468', near: '#3c4480',
    groundTop: '#6b6f86', ground: '#4c5064', groundDark: '#31344a',
    brick: '#7a7f98', brickDark: '#4c5064',
    block: '#f0c04a', blockDark: '#a87f22',
    stone: '#8a90a8', stoneDark: '#575c72',
    pipe: '#37a35a', pipeDark: '#1f6b39',
    water: '#3aa7e0', waterDark: '#1f6f9e',
    accent: '#ffd166', veg: '#3a6a52', motif: 'city', night: true, music: 'shur',
  },
  damavand: {
    id: 'damavand', name: 'قلهٔ دماوند',
    sky: ['#123a5e', '#4f8cc0', '#dff0ff'],
    sun: '#ffffff',
    far: '#7fa6c8', mid: '#a8c6dd', near: '#e8f4ff',
    groundTop: '#f2fbff', ground: '#cfe2f0', groundDark: '#93b0c8',
    brick: '#bcd0e0', brickDark: '#879db0',
    block: '#ffd45e', blockDark: '#c69a2a',
    stone: '#aab8c4', stoneDark: '#76838f',
    pipe: '#4f9ed0', pipeDark: '#2a6a94',
    water: '#68d0f0', waterDark: '#2f88b0',
    accent: '#8fd8ff', veg: '#3f7a6a', motif: 'snow', night: false, music: 'homayoun',
  },
};

/* ---------------------------------- مراحل -------------------------------- */
/* length = طول مرحله به تعداد خانه | pool = مخزن قطعه‌ها | boss = مرحلهٔ رئیس */
export const LEVELS = [
  { n: 1, theme: 'alley',  name: 'کوچهٔ کاهگلی',   length: 168, diff: 1, coins: 40, pool: 'easy',   enemies: ['ladybug', 'turtle'],              hazard: [],           boss: null,      gift: 'mushroom' },
  { n: 2, theme: 'bazaar', name: 'بازار بزرگ',      length: 190, diff: 2, coins: 55, pool: 'easy',   enemies: ['ladybug', 'turtle', 'bee'],        hazard: ['spike'],    boss: null,      gift: 'mushroom' },
  { n: 3, theme: 'garden', name: 'گلستان ارم',      length: 205, diff: 3, coins: 60, pool: 'mix',    enemies: ['ladybug', 'bee', 'plant'],         hazard: ['water'],    boss: null,      gift: 'fire' },
  { n: 4, theme: 'desert', name: 'کاروانسرای کویر', length: 200, diff: 4, coins: 60, pool: 'mix',    enemies: ['turtle', 'bee', 'plant'],          hazard: ['spike'],    boss: 'div',     gift: 'fire' },
  { n: 5, theme: 'qanat',  name: 'قنات زیرزمینی',   length: 215, diff: 5, coins: 65, pool: 'hard',   enemies: ['ghost', 'ladybug', 'plant'],       hazard: ['water'],    boss: null,      gift: 'star' },
  { n: 6, theme: 'persepolis', name: 'تخت جمشید',   length: 225, diff: 6, coins: 70, pool: 'hard',   enemies: ['spikeball', 'turtle', 'bee'],      hazard: ['spike'],    boss: null,      gift: 'fire' },
  { n: 7, theme: 'tehran', name: 'بام‌های تهران',   length: 235, diff: 7, coins: 75, pool: 'hard',   enemies: ['bee', 'ghost', 'ladybug', 'turtle'], hazard: ['void'],   boss: null,      gift: 'feather' },
  { n: 8, theme: 'damavand', name: 'قلهٔ دماوند',   length: 215, diff: 8, coins: 80, pool: 'hard',   enemies: ['spikeball', 'bee', 'ghost'],       hazard: ['water'],    boss: 'dragon',  gift: 'fire' },
];

/* ---------------------------------- فروشگاه ------------------------------- */
export const SHOP_ITEMS = [
  { id: 'life',      name: 'جان اضافه',            desc: 'یک جان بیشتر برای هر شروع', icon: '❤️', price: 120, repeat: true },
  { id: 'permFeather', name: 'پَر سیمرغ همیشگی',    desc: 'پرش دوم در همهٔ مرحله‌ها',   icon: '🪶', price: 1400, repeat: false },
  { id: 'bankShield', name: 'سپرِ سکه',             desc: 'با هر آسیب فقط ۱۰ سکه کم می‌شود', icon: '🛡️', price: 800, repeat: false },
  { id: 'magnet',    name: 'آهن‌ربای سکه',          desc: 'سکه‌های نزدیک به سمت تو می‌آیند', icon: '🧲', price: 1000, repeat: false },
  { id: 'char_niloofar', name: 'نیلوفر',           desc: 'شخصیت تازه: تند و تیز',      icon: '👧', price: 900, repeat: false, char: 'niloofar' },
  { id: 'char_norouz',   name: 'استاد نوروز',       desc: 'شخصیت تازه: پرش بلند',       icon: '🧙', price: 1800, repeat: false, char: 'norouz' },
];

/* -------------------------------- دستاوردها ------------------------------ */
export const ACHIEVEMENTS = [
  { id: 'firstStep', name: 'اولین قدم',        desc: 'مرحلهٔ اول را تمام کن',                icon: '🌟' },
  { id: 'coin50',    name: 'سکه‌چی',           desc: '۵۰ سکه در یک مرحله جمع کن',             icon: '🪙' },
  { id: 'noHit',     name: 'دست‌نخورده',       desc: 'یک مرحله را بدون آسیب تمام کن',          icon: '🛡️' },
  { id: 'allMush',   name: 'قارچ‌شناس',        desc: '۲۰۰ قارچ جمع کن',                      icon: '🍄' },
  { id: 'boss1',     name: 'دیو‌کُش',          desc: 'دیو کویر را شکست بده',                  icon: '⚔️' },
  { id: 'boss2',     name: 'اژدهاکُش',         desc: 'اژدهای دماوند را شکست بده',             icon: '🐉' },
  { id: 'endless1k', name: 'دوندهٔ ابریشم',    desc: 'در حالت بی‌پایان ۱۰۰۰ امتیاز بگیر',      icon: '🏃' },
  { id: 'combo5',    name: 'ضربهٔ زنجیره‌ای',   desc: '۵ دشمن را بدون فرود آمدن بزن',          icon: '💥' },
  { id: 'perfect8',  name: 'قهرمان ایران',     desc: 'همهٔ ۸ مرحله را تمام کن',               icon: '🏆' },
  { id: 'starMan',   name: 'ستارهٔ اقبال',     desc: 'با ستاره ۸ دشمن را نابود کن',            icon: '✨' },
];

export const DIFFICULTY_LABEL = { 1: 'آسان', 2: 'آسان', 3: 'متوسط', 4: 'متوسط', 5: 'سخت', 6: 'سخت', 7: 'خیلی سخت', 8: 'افسانه‌ای' };
