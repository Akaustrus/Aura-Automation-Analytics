<?php
if (empty($_GET['sin_shell'])): ?>
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

    (function () {
        var shell = document.getElementById('appShell');
        var btn   = document.getElementById('appMenuBtn');
        if (!shell || !btn) return;
        function close() { shell.classList.remove('menu-open'); }
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            shell.classList.toggle('menu-open');
        });
        document.addEventListener('click', function (e) {
            if (shell.classList.contains('menu-open') && !e.target.closest('.app-sidebar') && !e.target.closest('.app-menu-btn')) close();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close();
        });
    })();
</script>
</body>
</html>