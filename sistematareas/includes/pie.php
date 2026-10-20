<?php
// El layout solo se cierra para las vistas (las acciones AJAX salen por api.php).
if (!isset($_GET['accion'])): ?>
    </main>
</div>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/tareas.js"></script>
</body>
</html>
