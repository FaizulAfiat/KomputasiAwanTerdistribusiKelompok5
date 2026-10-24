<?php
if (defined('MEMBER_NAMA') && defined('MEMBER_NIM')): ?>
<div class="served-by" role="status">
  Anda dilayani oleh laman <?= htmlspecialchars(MEMBER_NAMA) ?> (<?= htmlspecialchars(MEMBER_NIM) ?>)
</div>
<?php endif; ?>