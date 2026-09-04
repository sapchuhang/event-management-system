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
    // ── Non-intrusive Top Progress Bar for Page Transitions ────────
    (function () {
        const bar = document.getElementById('page-progress-bar');
        if (!bar) return;

        let progressTimer = null;
        let safetyTimer = null;

        function setWidth(w, duration) {
            bar.style.transition = `width ${duration || 300}ms cubic-bezier(0.1, 0.9, 0.2, 1), opacity 0.2s ease`;
            bar.style.width = w + '%';
        }

        function startProgress() {
            clearTimeout(progressTimer);
            clearTimeout(safetyTimer);

            bar.style.transition = 'none';
            bar.style.width = '0%';
            bar.style.opacity = '1';

            // Smoothly animate towards 85%
            requestAnimationFrame(() => {
                setWidth(25, 200);
                progressTimer = setTimeout(() => setWidth(65, 500), 220);
                progressTimer = setTimeout(() => setWidth(85, 800), 750);
            });

            // Safety timeout: if page doesn't unload within 3.5 seconds (e.g. download or aborted nav), reset smoothly
            safetyTimer = setTimeout(() => {
                finishProgress();
            }, 3500);
        }

        function finishProgress() {
            clearTimeout(progressTimer);
            clearTimeout(safetyTimer);

            setWidth(100, 200);
            setTimeout(() => {
                bar.style.transition = 'opacity 0.25s ease';
                bar.style.opacity = '0';
                setTimeout(() => {
                    bar.style.transition = 'none';
                    bar.style.width = '0%';
                }, 260);
            }, 220);
        }

        // Intercept legitimate internal navigation links only
        document.addEventListener('click', function (e) {
            // If another script called e.preventDefault(), do not trigger loader
            if (e.defaultPrevented) return;

            // Only respond to main left click
            if (e.button !== 0) return;

            // Skip if user holds modifier keys (new tab, new window, save link)
            if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;

            const link = e.target.closest('a');
            if (!link) return;

            // Skip links explicitly intended for actions/modals/toggles
            if (link.hasAttribute('data-bs-toggle') ||
                link.hasAttribute('data-bs-target') ||
                link.getAttribute('role') === 'button' ||
                link.classList.contains('dropdown-toggle') ||
                link.hasAttribute('onclick')) {
                return;
            }

            const href = link.getAttribute('href');
            if (!href) return;

            // Skip non-navigating links
            if (href === '#' || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
                return;
            }

            // Skip downloads & exports
            if (link.hasAttribute('download') || href.includes('export_') || href.match(/\.(csv|xlsx|pdf|zip|txt)($|\?)/i)) {
                return;
            }

            // Skip target _blank
            if (link.target === '_blank') return;

            // Check origin
            try {
                const url = new URL(href, window.location.href);
                if (url.origin !== window.location.origin) return;
                // Skip if exact same URL including hash
                if (url.href === window.location.href) return;
            } catch (_) {
                return;
            }

            // Start smooth top bar
            startProgress();
        });

        // Also trigger on select dropdown changes that navigate via location = ...
        document.addEventListener('change', function(e) {
            const select = e.target.closest('select');
            if (select && select.hasAttribute('onchange') && select.getAttribute('onchange').includes('location')) {
                startProgress();
            }
        });

        // On page load/restore
        window.addEventListener('load', finishProgress);
        if (document.readyState === 'complete') finishProgress();
        window.addEventListener('pageshow', function (e) {
            finishProgress();
        });
    })();
</script>

<script>
    // ── Mobile sidebar toggle ──────────────────────────────
    (function () {
        const sidebar = document.getElementById('mainSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const toggle = document.getElementById('sidebarToggle');

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
        var datepickerInit = false;
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

        // Check AGM titles and handle transport allowance
        function handleAgmAllowance(titleSelector, allowanceSelector) {
            const titleVal = $(titleSelector).val() || '';
            const isAgm = /agm/i.test(titleVal) ||
                /वार्षिक साधारण सभा/i.test(titleVal) ||
                /साधारण सभा/i.test(titleVal) ||
                /annual general meeting/i.test(titleVal) ||
                /general meeting/i.test(titleVal);
            if (isAgm) {
                $(allowanceSelector).val('500.00');
                $(allowanceSelector).prop('readonly', true);
                if (!$(allowanceSelector).parent().next('.agm-note').length) {
                    $(allowanceSelector).parent().after('<div class="form-text text-success fw-medium agm-note"><i class="fas fa-info-circle me-1"></i>AGM events require a transport allowance of NPR 500.00.</div>');
                }
            } else {
                $(allowanceSelector).prop('readonly', false);
                $(allowanceSelector).parent().next('.agm-note').remove();
            }
        }

        $(document).on('input', '#add_event_title', function () {
            handleAgmAllowance('#add_event_title', '#add_event_allowance_amount');
        });

        $(document).on('input', '#edit_event_title', function () {
            handleAgmAllowance('#edit_event_title', '#edit_event_allowance_amount');
        });

        $(document).on('click', '.btn-edit-event', function () {
            $('#edit_event_id').val($(this).data('id'));
            $('#edit_event_title').val($(this).data('title'));
            $('#edit_event_date').val($(this).data('date'));
            $('#edit_event_location').val($(this).data('location'));
            $('#edit_event_status').val($(this).data('status'));
            $('#edit_event_allowance_amount').val($(this).data('allowance'));
            // Update the visible Nepali datepicker input
            $('#edit-nepali-datepicker').val($(this).data('date'));

            // Evaluate on load
            handleAgmAllowance('#edit_event_title', '#edit_event_allowance_amount');
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
                if ($msg['type'] === 'success')
                    $icon = 'success';
                if ($msg['type'] === 'error' || $msg['type'] === 'danger')
                    $icon = 'error';
                if ($msg['type'] === 'warning')
                    $icon = 'warning';
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