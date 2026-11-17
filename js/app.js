// menu do usuário (todas as páginas)
(function () {
  const um = document.getElementById('um'), b = document.getElementById('umb');
  if (um && b) {
    b.onclick = e => { e.stopPropagation(); const o = um.classList.toggle('open'); b.setAttribute('aria-expanded', o) };
    document.addEventListener('click', e => { if (!um.contains(e.target)) { um.classList.remove('open'); b.setAttribute('aria-expanded', false) } });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { um.classList.remove('open'); b.focus() } });
  }
  // páginas internas: menu mobile + toast (main.js só carrega na home)
  if (!document.body.classList.contains('is-home')) {
    const nav = document.getElementById('nav'), bg = document.getElementById('burger');
    if (bg) bg.onclick = () => { const o = nav.classList.toggle('open'); bg.setAttribute('aria-expanded', o) };
    const t = document.getElementById('toast'); let tt;
    document.querySelectorAll('[data-toast]').forEach(a => a.onclick = e => { e.preventDefault(); t.textContent = a.dataset.msg || 'ยังไม่มีการอัพเดท'; t.classList.add('on'); clearTimeout(tt); tt = setTimeout(() => t.classList.remove('on'), 2400) });
  }
})();

// Toast notifications (flash messages)
(function(){
  function showToast(type, message){
    const container = document.querySelector('.toast-container');
    if(!container){
      const div = document.createElement('div');
      div.className = 'toast-container';
      document.body.appendChild(div);
    }
    const icons = {ok:'✓',err:'✕',warn:'!'};
    const toast = document.createElement('div');
    toast.className = 'toast ' + type;
    toast.innerHTML = '<div class="toast-icon">' + (icons[type] || 'i') + '</div><div class="toast-content">' + message + '</div><button class="toast-close" aria-label="ปิด">✕</button>';
    const container2 = document.querySelector('.toast-container');
    container2.appendChild(toast);
    requestAnimationFrame(()=>{ toast.classList.add('show') });
    const close = toast.querySelector('.toast-close');
    close.onclick = ()=>hideToast(toast);
    setTimeout(()=>hideToast(toast), 4000);
  }
  function hideToast(toast){
    toast.classList.remove('show');
    toast.classList.add('hide');
    setTimeout(()=>{ toast.remove() }, 400);
  }
  if(window.__flashMessages){
    window.__flashMessages.forEach(([type,msg])=>showToast(type,msg));
    delete window.__flashMessages;
  }
})();

/* Discord autocomplete � input.dca + <script type="application/json" id="dcUsers"> */
(function(){
  const el=document.getElementById('dcUsers');if(!el)return;
  let users=[];try{users=JSON.parse(el.textContent)}catch(e){return}
  const box=document.createElement('div');box.id='dcSug';
  box.style.cssText='position:fixed;z-index:9999;background:var(--card2,#182230);border:1px solid var(--line,rgba(255,255,255,.15));border-radius:10px;max-height:220px;overflow:auto;display:none;box-shadow:0 10px 30px rgba(0,0,0,.5)';
  document.body.appendChild(box);
  const close=()=>{box.style.display='none';cur=null};
  let cur=null;
  const place=()=>{if(!cur||box.style.display==='none')return;const r=cur.getBoundingClientRect();if(r.bottom<0||r.top>innerHeight){close();return}box.style.left=r.left+'px';box.style.top=(r.bottom+4)+'px';box.style.width=r.width+'px'};
  document.addEventListener('click',e=>{if(!box.contains(e.target))close()});
  document.addEventListener('scroll',place,true);
  addEventListener('resize',place);
  document.querySelectorAll('input.dca').forEach(inp=>{
    inp.addEventListener('input',()=>{
      const q=inp.value.trim().toLowerCase();
      if(inp.readOnly||q.length<2){close();return}
      const hit=users.filter(u=>u.n.includes(q)).slice(0,8);
      if(!hit.length){close();return}
      cur=inp;box.innerHTML='';
      hit.forEach(u=>{
        const d=document.createElement('div');d.style.cssText='padding:.5rem .8rem;cursor:pointer;font-size:.85rem;color:var(--t1,#e8eef2)';
        d.textContent=(u.d?u.d+' (@'+u.u+')':'@'+u.u)+(u.g?' · '+u.g:'');
        d.onmouseenter=()=>d.style.background='var(--elev,rgba(255,255,255,.08))';
        d.onmouseleave=()=>d.style.background='';
        const other=(u.u+' '+(u.d||'')).toLowerCase();
        d.onclick=()=>{inp.value=(u.g&&other.indexOf(q)<0&&u.g.toLowerCase().includes(q))?u.g:u.u;if(!inp.hasAttribute('data-nolock')){inp.readOnly=true;inp.style.opacity='.65';inp.title='????????? � ?????????'}close()};
        box.appendChild(d);
      });
      const r=inp.getBoundingClientRect();
      box.style.left=r.left+'px';box.style.top=(r.bottom+4)+'px';box.style.width=r.width+'px';box.style.display='block';
    });
    inp.addEventListener('blur',()=>setTimeout(close,150));
  });
})();
