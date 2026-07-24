<?php
// includes/footer.php
?>
                </div> <!-- End Main Content -->
            </div> <!-- End Col-md-10 -->
        </div> <!-- End Row -->
    </div> <!-- End Container -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Nepali Datepicker (local) -->
    <script src="<?= BASE_URL ?>assets/js/vendor/jquery.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/vendor/nepaliDatePicker.min.js"></script>
    <script src="<?= BASE_URL ?>assets/js/main.js"></script>

    <script>
        // ── Mobile sidebar toggle ──────────────────────────────
        (function () {
            const sidebar  = document.getElementById('mainSidebar');
            const overlay  = document.getElementById('sidebarOverlay');
            const toggle   = document.getElementById('sidebarToggle');

            if (!toggle || !sidebar || !overlay) return;

            function openSidebar() {
                sidebar.classList.add('mobile-open');
                overlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            }
            function closeSidebar() {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }

            toggle.addEventListener('click', openSidebar);
            overlay.addEventListener('click', closeSidebar);
        })();

        // ── Nepali datepicker initialisation ──────────────────
        $(document).ready(function () {
            var datepickerInit     = false;
            var editDatepickerInit = false;

            $('#addEventModal').on('shown.bs.modal', function () {
                if (!datepickerInit) {
                    $('#nepali-datepicker').nepaliDatePicker({
                        dateFormat: '%y-%m-%d',
                        closeOnDateSelect: true
                    });
                    datepickerInit = true;
                }
            });
            $('#addEventModal').on('click', '#nepali-datepicker', function () {
                $(this).nepaliDatePicker('show');
            });

            // Edit event modal datepicker
            $('#editEventModal').on('shown.bs.modal', function () {
                if (!editDatepickerInit) {
                    $('#edit-nepali-datepicker').nepaliDatePicker({
                        dateFormat: '%y-%m-%d',
                        closeOnDateSelect: true
                    });
                    editDatepickerInit = true;
                }
            });
            $('#editEventModal').on('click', '#edit-nepali-datepicker', function () {
                $(this).nepaliDatePicker('show');
            });

            // Populate Edit modal fields when opened
            $(document).on('click', '.btn-edit-event', function () {
                $('#edit_event_id').val($(this).data('id'));
                $('#edit_event_title').val($(this).data('title'));
                $('#edit_event_date').val($(this).data('date'));
                $('#edit_event_location').val($(this).data('location'));
                $('#edit_event_status').val($(this).data('status'));
                // Update the visible Nepali datepicker input
                $('#edit-nepali-datepicker').val($(this).data('date'));
            });
        });
    </script>

    <?php 
    $flashMessages = getFlashMessages();
    if (!empty($flashMessages)): 
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const Toast = Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3500,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', Swal.stopTimer);
                    toast.addEventListener('mouseleave', Swal.resumeTimer);
                }
            });

            <?php foreach ($flashMessages as $msg): ?>
                <?php 
                    $icon = 'info';
                    if ($msg['type'] === 'success') $icon = 'success';
                    if ($msg['type'] === 'error' || $msg['type'] === 'danger') $icon = 'error';
                    if ($msg['type'] === 'warning') $icon = 'warning';
                ?>
                Toast.fire({
                    icon: '<?= $icon ?>',
                    title: <?= json_encode($msg['message']) ?>
                });
            <?php endforeach; ?>
        });
    </script>
    <?php endif; ?>
</body>
</html>
