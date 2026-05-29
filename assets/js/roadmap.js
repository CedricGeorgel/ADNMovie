(function() {
    document.querySelectorAll('.report-type-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.report-type-btn').forEach(function(b) {
                b.style.borderColor = 'var(--border)';
                b.style.color = 'var(--text-dim)';
                b.style.background = 'var(--card-bg)';
            });
            btn.style.borderColor = 'var(--pastel-blue)';
            btn.style.color = 'var(--text-main)';
            btn.style.background = 'rgba(167,199,231,0.07)';

            var val = btn.dataset.val;
            document.querySelector('input[name="report_type"][value="' + val + '"]').checked = true;
        });
    });
    var bugBtn = document.querySelector('.report-type-btn[data-val="bug"]');
    if (bugBtn) bugBtn.click();

    var form = document.getElementById('reportForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var type  = document.querySelector('input[name="report_type"]:checked').value;
            var title = document.getElementById('report-title').value.trim();
            var desc  = document.getElementById('report-desc').value.trim();
            var fb    = document.getElementById('report-feedback');

            if (!title) { fb.textContent = 'Le titre est obligatoire.'; return; }

            var btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            fb.textContent = 'Envoi...';

            fetch('api/api_submit_report.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ type: type, title: title, description: desc })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    fb.style.color = 'var(--pastel-blue)';
                    fb.textContent = 'Merci, votre signal a été transmis.';
                    document.getElementById('report-title').value = '';
                    document.getElementById('report-desc').value  = '';
                } else {
                    fb.style.color = 'var(--danger)';
                    fb.textContent = data.message || 'Erreur.';
                }
            })
            .catch(function() {
                fb.style.color = 'var(--danger)';
                fb.textContent = 'Erreur réseau.';
            })
            .finally(function() { btn.disabled = false; });
        });
    }
})();
