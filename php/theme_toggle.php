<?php
// Theme toggle button and JS - include after profile dropdown
?>
<script>
function toggleTheme(){var h=document.documentElement,i=document.getElementById('themeIcon'),l=document.getElementById('themeLabel'),d=h.getAttribute('data-theme')==='dark';if(d){h.removeAttribute('data-theme');localStorage.setItem('theme','light');if(i)i.className='fas fa-moon';if(l)l.textContent='Donker thema'}else{h.setAttribute('data-theme','dark');localStorage.setItem('theme','dark');if(i)i.className='fas fa-sun';if(l)l.textContent='Licht thema'}}
(function(){var s=localStorage.getItem('theme');if(s==='dark'||(!s&&window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches)){var i=document.getElementById('themeIcon'),l=document.getElementById('themeLabel');if(i)i.className='fas fa-sun';if(l)l.textContent='Licht thema'}})();
</script>