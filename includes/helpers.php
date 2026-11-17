<?php
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function asset_exists($rel,$root){return $rel!=='' && is_file(str_replace('/', DIRECTORY_SEPARATOR, $root.'/'.ltrim($rel,'/')));}
function fill($s,$vars){foreach($vars as $k=>$v)$s=str_replace('{'.$k.'}',e($v),$s);return $s;}
function chk(){return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>';}
function dl_icon($s=14){return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';}
function logo_html($c,$root,$prefix=''){return asset_exists($c['logo'],$root)?'<img src="'.e($prefix.$c['logo']).'" alt="'.e($c['brand']).'" width="32" height="32">':e($c['initial']);}
function apk_btn($c,$class,$label,$size=14,$file=null,$url=null,$page=false){
  $u=$url??$c['links']['apk'];
  $f=$file??$c['apk_file'];
  if($u==='')return '<a class="'.$class.'" href="#" data-toast data-msg="ยังไม่มีให้ดาวน์โหลด">'.dl_icon($size).'<span>'.e($label).'</span></a>';
  $dl=$page?'':' download="'.e($f).'"';
  return '<a class="'.$class.'" href="'.e($u).'"'.$dl.' target="_blank" rel="noopener noreferrer">'.dl_icon($size).'<span>'.e($label).'</span></a>';
}
function ext($u){return $u===''?'href="#" data-toast':'href="'.e($u).'" target="_blank" rel="noopener noreferrer"';}
function dc_icon($s=18){return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.3 4.4a19.8 19.8 0 0 0-4.9-1.5c-.2.4-.4.9-.6 1.3a18.3 18.3 0 0 0-5.500 0c-.2-.4-.4-.9-.6-1.300A19.700 19.700 0 0 0 3.700 4.400C.5 9-.3 13.600.1 18.100a19.900 19.900 0 0 0 6 3c.5-.6.9-1.300 1.200-2-.7-.3-1.300-.6-1.900-.9l.4-.3c3.900 1.800 8.200 1.800 12.100 0l.4.3c-.6.400-1.200.7-1.900.9.4.7.8 1.400 1.200 2a19.800 19.800 0 0 0 6-3c.5-5.200-.8-9.700-3.500-13.700M8 15.300c-1.200 0-2.200-1.100-2.200-2.400s1-2.400 2.200-2.400 2.200 1.100 2.200 2.400-1 2.400-2.200 2.400m8 0c-1.200 0-2.200-1.100-2.200-2.400s1-2.400 2.200-2.400 2.200 1.100 2.200 2.400-1 2.400-2.200 2.400"/></svg>';}
function icon($n,$s=14){static $i=[
 'clock'=>'<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
 'pin'=>'<path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
 'gift'=>'<polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>',
 'users'=>'<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
 'cal'=>'<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'];
 return '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($i[$n]??'').'</svg>';}
