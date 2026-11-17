const $ = s => document.querySelector(s), $$ = s => [...document.querySelectorAll(s)];
const toast = $('#toast'); let tt;
const say = m => { toast.textContent = m; toast.classList.add('on'); clearTimeout(tt); tt = setTimeout(() => toast.classList.remove('on'), 2400) };
$$('[data-toast]').forEach(a => a.onclick = e => { e.preventDefault(); say(a.dataset.msg || 'ยังไม่มีการอัพเดท') });
// scroll: progress, header, parallax
const hdr = $('#hdr'), prog = $('#prog'), hbg = $('#hbg'), hc = $('#hc'), pls = $$('.pl'); let tick = false;
function onScroll() {
    const y = scrollY, max = document.documentElement.scrollHeight - innerHeight;
    prog.style.transform = `scaleX(${max > 0 ? y / max : 0})`; hdr.classList.toggle('show', y > innerHeight * .5);
    hbg.style.transform = `translate3d(0,${y * .25}px,0)`; hc.style.transform = `translate3d(0,${y * .12}px,0)`; hc.style.opacity = Math.max(0, 1 - y / (innerHeight * .8));
    pls.forEach(p => p.style.transform = `translate3d(0,${y * parseFloat(p.dataset.s) * -1}px,0)`); tick = false
}
addEventListener('scroll', () => { if (!tick) { tick = true; requestAnimationFrame(onScroll) } }, { passive: true }); onScroll();
// mobile nav
const nav = $('#nav'), bg = $('#burger');
bg.onclick = () => { const o = nav.classList.toggle('open'); bg.setAttribute('aria-expanded', o) };
nav.onclick = e => { if (e.target.tagName === 'A') { nav.classList.remove('open'); bg.setAttribute('aria-expanded', false) } };
// active nav
const links = $$('#nav a');
const so = new IntersectionObserver(es => es.forEach(en => { if (en.isIntersecting) links.forEach(a => a.classList.toggle('on', a.getAttribute('href') === '#' + en.target.id)) }), { rootMargin: '-45% 0px -50% 0px' });
['hero', 'download', 'mockups', 'samples', 'tutorial'].forEach(id => { const el = document.getElementById(id); if (el) so.observe(el) });
// reveal
const ro = new IntersectionObserver(es => es.forEach(en => { if (en.isIntersecting) { en.target.classList.add('in'); ro.unobserve(en.target) } }), { threshold: .12 });
$$('.rv').forEach(el => ro.observe(el));
// download carousel: ลากเพื่อเลื่อน
document.querySelectorAll('.dtrack').forEach(dt => {
    let drag = false, sx = 0, sl = 0, moved = 0, suppress = false;
    dt.addEventListener('pointerdown', e => { drag = true; sx = e.clientX; sl = dt.scrollLeft; moved = 0 });
    dt.addEventListener('pointermove', e => {
        if (!drag) return; const dx = e.clientX - sx;
        if (Math.abs(dx) > 4 && !dt.hasPointerCapture(e.pointerId)) dt.setPointerCapture(e.pointerId);
        moved = Math.max(moved, Math.abs(dx)); dt.scrollLeft = sl - dx
    });
    ['pointerup', 'pointercancel'].forEach(ev => dt.addEventListener(ev, () => { drag = false; if (moved > 8) suppress = true }));
    dt.addEventListener('click', e => { if (suppress) { suppress = false; e.preventDefault(); e.stopPropagation() } }, { capture: true })
});

