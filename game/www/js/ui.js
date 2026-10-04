/* ui.js — مدیریت صفحه‌ها، کارت‌ها (فروشگاه/شخصیت/دستاورد)، توست و لرزش */

import { CHARACTERS, SHOP_ITEMS, ACHIEVEMENTS, LEVELS, DIFFICULTY_LABEL, VERSION } from './config.js';
import { fa } from './utils.js';

export class UI {
  constructor(save, handlers = {}) {
    this.save = save;
    this.handlers = handlers;
    this.current = 'screen-loading';
    this.toastTimer = null;
  }

  el(id) { return document.getElementById(id); }

  show(id) {
    for (const s of document.querySelectorAll('.screen')) s.classList.remove('active');
    const el = this.el(id);
    if (el) el.classList.add('active');
    this.current = id;
  }

  hideScreens() {
    for (const s of document.querySelectorAll('.screen')) s.classList.remove('active');
    this.current = null;
  }

  toast(msg, ms = 1800) {
    const t = this.el('toast');
    if (!t) return;
    t.textContent = msg;
    t.classList.remove('hidden');
    clearTimeout(this.toastTimer);
    this.toastTimer = setTimeout(() => t.classList.add('hidden'), ms);
  }

  vibrate(pattern = 18) {
    if (!this.save.settings.vibrate) return;
    try { navigator.vibrate?.(pattern); } catch (e) {}
  }

  /* ------------------------------- صفحه‌ها ------------------------------- */
  renderTitle() {
    this.el('stat-bank').textContent = fa(this.save.bank);
    this.el('stat-mush').textContent = fa(this.save.totalMushrooms);
    const stars = Object.values(this.save.stars).reduce((a, b) => a + b, 0);
    this.el('stat-stars').textContent = fa(stars);
    const v = this.el('app-version');
    if (v) v.textContent = fa(VERSION);
  }

  renderMap() {
    const grid = this.el('map-grid');
    if (!grid) return;
    grid.innerHTML = '';
    LEVELS.forEach((lv, i) => {
      const unlocked = this.save.unlockedLevel >= lv.n;
      const stars = this.save.stars[i] || 0;
      const best = this.save.best[i] || 0;
      const card = document.createElement('button');
      card.className = 'map-card' + (unlocked ? '' : ' locked');
      card.innerHTML = `
        <div class="num">مرحله ${fa(lv.n)}</div>
        <div class="nm">${lv.name}</div>
        <div class="diff">سختی: ${DIFFICULTY_LABEL[lv.diff] || ''} ${lv.boss ? '👹' : ''}</div>
        <div class="stars">${'★'.repeat(stars)}${'☆'.repeat(3 - stars)}</div>
        <div class="best">${best ? 'رکورد: ' + fa(best) : 'بازی‌نشده'}</div>`;
      card.addEventListener('click', () => {
        if (!unlocked) { this.toast('🔒 اول مرحله‌های قبلی را تمام کن'); this.handlers.onSfx?.('denied'); return; }
        this.handlers.onStartLevel?.(i);
      });
      grid.appendChild(card);
    });
  }

  renderShop() {
    const list = this.el('shop-list');
    if (!list) return;
    list.innerHTML = '';
    const bankEl = document.createElement('div');
    bankEl.className = 'stats-row';
    bankEl.innerHTML = `<span>🪙 بانک شما: <b>${fa(this.save.bank)}</b></span>`;
    list.appendChild(bankEl);
    for (const item of SHOP_ITEMS) {
      const owned = this.save.owned.includes(item.id);
      const card = document.createElement('div');
      card.className = 'card' + (owned ? ' owned' : '');
      card.innerHTML = `
        <div class="ic">${item.icon}</div>
        <div class="info">
          <div class="nm">${item.name}</div>
          <div class="ds">${item.desc}</div>
          <div class="price">${owned ? 'خریداری‌شده ✅' : 'قیمت: ' + fa(item.price) + ' سکه'}</div>
        </div>`;
      const btn = document.createElement('button');
      btn.className = 'btn';
      btn.textContent = owned ? 'دارید' : 'خرید';
      btn.disabled = owned || this.save.bank < item.price;
      btn.addEventListener('click', () => this.handlers.onBuy?.(item));
      card.appendChild(btn);
      list.appendChild(card);
    }
  }

  renderChars() {
    const list = this.el('char-list');
    if (!list) return;
    list.innerHTML = '';
    for (const c of CHARACTERS) {
      const unlocked = c.unlock === 0 || this.save.owned.includes('char_' + c.id);
      const selected = this.save.character === c.id;
      const card = document.createElement('div');
      card.className = 'card' + (selected ? ' selected' : '') + (unlocked ? '' : ' locked');
      card.innerHTML = `
        <div class="ic">${c.icon}</div>
        <div class="info">
          <div class="nm">${c.name}</div>
          <div class="ds">${c.desc}</div>
          <div class="price">${unlocked ? (selected ? 'انتخاب‌شده ✨' : '') : 'قیمت: ' + fa(c.unlock) + ' سکه'}</div>
        </div>`;
      const btn = document.createElement('button');
      btn.className = 'btn';
      btn.textContent = unlocked ? (selected ? 'انتخاب‌شده' : 'انتخاب') : 'بازکردن';
      btn.disabled = selected;
      btn.addEventListener('click', () => this.handlers.onSelectChar?.(c, unlocked));
      card.appendChild(btn);
      list.appendChild(card);
    }
  }

  renderAchievements() {
    const list = this.el('ach-list');
    if (!list) return;
    list.innerHTML = '';
    for (const a of ACHIEVEMENTS) {
      const done = this.save.achievements.includes(a.id);
      const card = document.createElement('div');
      card.className = 'card' + (done ? ' owned' : '');
      card.innerHTML = `
        <div class="ic">${done ? a.icon : '🔒'}</div>
        <div class="info">
          <div class="nm">${a.name}</div>
          <div class="ds">${a.desc}</div>
          <div class="price">${done ? 'کسب‌شده ✅' : 'قفل'}</div>
        </div>`;
      list.appendChild(card);
    }
  }

  fillSettings() {
    const s = this.save.settings;
    const map = { 'set-music': 'music', 'set-sfx': 'sfx', 'set-vibrate': 'vibrate', 'set-lefthanded': 'leftHanded', 'set-shadows': 'shadows' };
    for (const id in map) {
      const el = this.el(id);
      if (el) el.checked = !!s[map[id]];
    }
    const q = this.el('set-quality');
    if (q) q.value = s.quality || 'auto';
  }

  /* راهنما و نکتهٔ مرحله روی صفحهٔ توقف */
  renderPauseTips(levelIndex, endless) {
    const el = this.el('pause-tips');
    if (!el) return;
    if (endless) {
      el.innerHTML = [
        '<div class="tip"><b>دوی بی‌پایان:</b> هرچه جلوتر بروی، دشمن‌ها بیشتر و سریع‌تر می‌شوند و پرتگاه‌ها بیشتر می‌شوند.</div>',
        '<div class="tip"><b>کنترل:</b> دکمهٔ 🔥 هم شوت گل آتش است و هم برای دویدن؛ با نگه‌داشتن ▲ بلندتر می‌پری.</div>',
        '<div class="tip"><b>هدف:</b> بیشترین امتیاز و بیشترین سکه — سکه‌ها به بانک می‌روند و در فروشگاه خرج می‌شوند.</div>',
      ].join('');
      return;
    }
    const lv = LEVELS[levelIndex];
    const tips = [];
    if (lv) tips.push(`<div class="tip"><b>مرحله ${fa(lv.n)} — ${lv.name}:</b> سختی «${DIFFICULTY_LABEL[lv.diff] || ''}»${lv.boss ? ' — در پایان این مرحله یک رئیس منتظر توست!' : ''}</div>`);
    tips.push('<div class="tip"><b>کنترل:</b> دکمهٔ 🔥 هم شوت گل آتش است و هم برای دویدن؛ با نگه‌داشتن ▲ بلندتر می‌پری.</div>');
    tips.push('<div class="tip"><b>نکته:</b> روی سر دشمن‌ها بپر تا نابود شوند؛ در نبرد رئیس هنگام درخشش طلایی روی سرش بپر (فقط از جلو با گل آتش هم می‌شود).</div>');
    if (lv && lv.hazard.includes('water')) tips.push('<div class="tip"><b>خطر این مرحله:</b> آب — پرش‌های بلند بزن و از ایستگاه ذخیره غافل نشو.</div>');
    if (lv && lv.hazard.includes('spike')) tips.push('<div class="tip"><b>خطر این مرحله:</b> نیزه‌ها — روی نوک نیزه‌ها فرود نیا؛ از لبه بپر.</div>');
    if (lv && lv.hazard.includes('void')) tips.push('<div class="tip"><b>خطر این مرحله:</b> پرتگاه — لبه‌ها را با احتیاط رد کن؛ اگر پَر سیمرغ داری با پرش دوم نجات پیدا می‌کنی.</div>');
    el.innerHTML = tips.join('');
  }

  renderComplete(info) {
    this.el('complete-title').textContent = info.boss ? '👑 دشمن بزرگ شکست خورد!' : '🎉 مرحله تمام شد!';
    this.el('complete-stars').textContent = '★'.repeat(info.stars) + '☆'.repeat(3 - info.stars);
    const table = this.el('complete-table');
    table.innerHTML = `
      <div><span>امتیاز</span><b>${fa(info.score)}</b></div>
      <div><span>سکه‌ها</span><b>${fa(info.coins)}</b></div>
      <div><span>پاداش زمان</span><b>${fa(info.timeBonus)}</b></div>
      <div><span>دشمنان شکست‌خورده</span><b>${fa(info.kills)}</b></div>
      <div><span>قارچ‌ها</span><b>${fa(info.mushrooms)}</b></div>
      <div><span>آسیب‌ها</span><b>${fa(info.hurt)}</b></div>`;
    const nextBtn = this.el('btn-next');
    if (nextBtn) nextBtn.style.display = info.hasNext ? '' : 'none';
  }

  renderGameOver(title, text) {
    this.el('gameover-title').textContent = title;
    this.el('gameover-text').textContent = text;
  }

  setLoading(pct, text) {
    const fill = this.el('loading-fill');
    if (fill) fill.style.width = Math.round(pct * 100) + '%';
    const t = this.el('loading-text');
    if (t && text) t.textContent = text;
  }
}
