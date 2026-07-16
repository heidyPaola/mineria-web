<?php
// includes/footer.php
// Este archivo contiene los scripts y cierre de etiquetas
?>

<?php if (isset($_SESSION['user_id'])): ?>
    </div> <!-- Cierre .main-content -->
</div> <!-- Cierre .wrapper -->
<?php else: ?>
    </div> <!-- Cierre .container-fluid -->
<?php endif; ?>

<!-- Scripts JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<!-- Chart.js para gráficos -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<!-- SweetAlert2 para alertas bonitas -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<!-- FullCalendar JS -->
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales/es.js'></script>


<!-- Scripts personalizados -->
<script src="/MINERIA/assets/js/main.js"></script>
<script src="/MINERIA/assets/js/api.js"></script>

<?php if (basename($_SERVER['PHP_SELF']) == 'index.php'): ?>
<script src="/MINERIA/assets/js/dashboard.js"></script>
<?php endif; ?>

</body>
</html>