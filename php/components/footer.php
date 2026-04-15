</div>
</div>

<script src="<?= $prefix ?>js/nav.js"></script>
<?php if (str_contains(basename($_SERVER['PHP_SELF']), 'planejamento') || str_contains(basename($_SERVER['PHP_SELF']), 'agenda_professores') || str_contains(basename($_SERVER['PHP_SELF']), 'turmas')): ?>
    <script src="<?= $prefix ?>js/gantt.js"></script>
    <script src="<?= $prefix ?>js/calendar.js"></script>
    <script src="<?= $prefix ?>js/agenda_professores.js"></script>
    <script src="<?= $prefix ?>js/planejamento_view.js"></script>
<?php endif; ?>

<?php include __DIR__ . '/modals/all_modals.php'; ?>


</body>

</html>