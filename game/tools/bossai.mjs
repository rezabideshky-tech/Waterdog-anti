/* tools/bossai.mjs — هوش مصنوعی نبرد رئیس برای ربات‌های آزمون
   (هم در boss-test.mjs و هم در validate.mjs استفاده می‌شود) */

/** ورودی ربات را برای یک فریم نبرد رئیس تنظیم می‌کند */
export function bossThink(world, input, i) {
  const b = world.boss;
  const pl = world.player;
  if (!b || !pl || pl.dead) return;
  const dx = b.cx - pl.cx;
  const dist = Math.abs(dx);
  const vulnerable = b.vulnStomp;
  const setDir = (d) => { input.state.right = d > 0; input.state.left = d < 0; };
  const margin = 10;
  const roomLeft = pl.x - (b.arena.x0 + margin);
  const roomRight = (b.arena.x1 - margin) - (pl.x + pl.w);
  const runAway = () => {
    // به سمتی که جا باز است فرار کن
    if (dx > 0) setDir(roomLeft > 24 ? -1 : 1);
    else setDir(roomRight > 24 ? 1 : -1);
  };

  input.state.jump = false;
  if (vulnerable) {
    // پنجرهٔ آسیب‌پذیری: نزدیک شو و روی سر بپر
    setDir(dx > 0 ? 1 : -1);
    if (dist < 50 && pl.onGround) input.state.jump = true;
    else if (!pl.onGround && pl.vy > 0 && dist < 34) input.state.jump = true;
  } else if (b.type === 'dragon') {
    const landing = Number.isFinite(b.diveX) ? b.diveX : null;
    const danger = (b.state === 'windup' || b.state === 'dive') && landing !== null;
    if (danger) {
      const gap = landing - pl.cx;
      if (Math.abs(gap) < 52) {
        const goLeft = gap > 0;
        const room = goLeft ? roomLeft : roomRight;
        setDir(room > 26 ? (goLeft ? -1 : 1) : (goLeft ? 1 : -1));
      } else setDir(0);
    } else if (dist < 120) runAway();
    else if (dist > 200) setDir(dx > 0 ? 1 : -1);
    else setDir(0);
  } else {
    // دیو: از تماس فرار کن و از دور با گل آتش بزن
    if (dist < 110) runAway();
    else if (dist > 200) setDir(dx > 0 ? 1 : -1);
    else setDir(0);
  }
  input.state.run = true;
  input.state.fire = !vulnerable && dist > 70 && dist < 240 && (i % 24) < 9;
}
