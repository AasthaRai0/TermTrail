    </main>
  </div>
</div>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toasts"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/lucide@0.441.0/dist/umd/lucide.min.js"></script>
<script src="<?= APP_URL ?>/frontend/assets/js/app.js"></script>
<?php foreach (($scripts ?? []) as $s): ?><script src="<?= APP_URL ?>/frontend/assets/js/<?= $s ?>"></script><?php endforeach; ?>
</body></html>
