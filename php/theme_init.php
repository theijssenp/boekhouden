<?php
// Theme initialization - include this in <head> to prevent flash of wrong theme
?>
<script>
(function(){var t=localStorage.getItem('theme');if(t==='dark'||(!t&&window.matchMedia&&window.matchMedia('(prefers-color-scheme:dark)').matches)){document.documentElement.setAttribute('data-theme','dark')}})();
</script>