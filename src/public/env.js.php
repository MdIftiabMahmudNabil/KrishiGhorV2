<?php 
header('Content-Type: application/javascript'); 
?>
window.__SUPABASE__ = {
  url: "<?= htmlspecialchars(getenv('SUPABASE_URL') ?: '', ENT_QUOTES) ?>",
  anon: "<?= htmlspecialchars(getenv('SUPABASE_ANON_KEY') ?: '', ENT_QUOTES) ?>"
};
