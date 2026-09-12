<?php
// Page actions for generate headers bypass main layout
if (!isset($_GET['action']) && ($vistaActual ?? '') !== 'ver'): ?>
    </main>
</div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.querySelectorAll('a[data-confirm]').forEach(function(a){
        a.addEventListener('click', function(e){
            if (!confirm(this.dataset.confirm)) e.preventDefault();
        });
    });
</script>
</body>
</html>