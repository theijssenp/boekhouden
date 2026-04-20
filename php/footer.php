    </div>
    
    <script>
        // Confirm delete function
        function confirmDelete(userId, username) {
            if (confirm(`Weet je zeker dat je gebruiker "${username}" wilt verwijderen?\n\nDeze actie kan niet ongedaan worden gemaakt.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'admin_users.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                form.appendChild(actionInput);
                
                const userIdInput = document.createElement('input');
                userIdInput.type = 'hidden';
                userIdInput.name = 'user_id';
                userIdInput.value = userId;
                form.appendChild(userIdInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Password strength indicator
        document.addEventListener('DOMContentLoaded', function() {
            const passwordInput = document.getElementById('password');
            if (passwordInput) {
                const strengthIndicator = document.createElement('div');
                strengthIndicator.className = 'password-strength';
                strengthIndicator.innerHTML = `
                    <div class="strength-bar">
                        <div class="strength-fill"></div>
                    </div>
                    <div class="strength-text">Wachtwoord sterkte: <span>Zeer zwak</span></div>
                `;
                passwordInput.parentNode.appendChild(strengthIndicator);
                
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    let strength = 0;
                    let text = 'Zeer zwak';
                    let color = '#dc3545';
                    
                    if (password.length >= 8) strength++;
                    if (/[A-Z]/.test(password)) strength++;
                    if (/[a-z]/.test(password)) strength++;
                    if (/[0-9]/.test(password)) strength++;
                    if (/[^A-Za-z0-9]/.test(password)) strength++;
                    
                    switch(strength) {
                        case 0:
                        case 1:
                            text = 'Zeer zwak';
                            color = '#dc3545';
                            break;
                        case 2:
                            text = 'Zwak';
                            color = '#fd7e14';
                            break;
                        case 3:
                            text = 'Matig';
                            color = '#ffc107';
                            break;
                        case 4:
                            text = 'Sterk';
                            color = '#28a745';
                            break;
                        case 5:
                            text = 'Zeer sterk';
                            color = '#20c997';
                            break;
                    }
                    
                    const fill = strengthIndicator.querySelector('.strength-fill');
                    const textSpan = strengthIndicator.querySelector('.strength-text span');
                    
                    fill.style.width = (strength * 20) + '%';
                    fill.style.backgroundColor = color;
                    textSpan.textContent = text;
                    textSpan.style.color = color;
                });
            }
            
            // Tab functionality
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    if (this.classList.contains('active')) {
                        e.preventDefault();
                    }
                });
            });
            
            // Form validation
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const requiredFields = this.querySelectorAll('[required]');
                    let valid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            valid = false;
                            field.style.borderColor = '#dc3545';
                        } else {
                            field.style.borderColor = '';
                        }
                    });
                    
                    if (!valid) {
                        e.preventDefault();
                        alert('Vul alle verplichte velden in (gemarkeerd met *)');
                    }
                });
            });
        });
        
        // Theme toggle functionality
        function toggleTheme() {
            const html = document.documentElement;
            const icon = document.getElementById('themeIcon');
            const label = document.getElementById('themeLabel');
            const isDark = html.getAttribute('data-theme') === 'dark';

            if (isDark) {
                html.removeAttribute('data-theme');
                localStorage.setItem('theme', 'light');
                if (icon) icon.className = 'fas fa-moon';
                if (label) label.textContent = 'Donker thema';
            } else {
                html.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                if (icon) icon.className = 'fas fa-sun';
                if (label) label.textContent = 'Licht thema';
            }
        }

        // Initialize theme from localStorage or system preference
        (function() {
            const saved = localStorage.getItem('theme');
            if (saved) {
                document.documentElement.setAttribute('data-theme', saved);
                if (saved === 'dark') {
                    const icon = document.getElementById('themeIcon');
                    const label = document.getElementById('themeLabel');
                    if (icon) icon.className = 'fas fa-sun';
                    if (label) label.textContent = 'Licht thema';
                }
            } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                document.documentElement.setAttribute('data-theme', 'dark');
                const icon = document.getElementById('themeIcon');
                const label = document.getElementById('themeLabel');
                if (icon) icon.className = 'fas fa-sun';
                if (label) label.textContent = 'Licht thema';
            }
        })();
    </script>
    
    <style>
        /* Tabs */
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 0;
        }
        
        .tab {
            padding: 12px 20px;
            background: var(--bg-card);
            border: 1px solid #e9ecef;
            border-bottom: none;
            border-radius: 8px 8px 0 0;
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .tab:hover {
            background: var(--gray-medium);
            color: var(--text-primary);
        }
        
        .tab.active {
            background: var(--bg-card);
            color: var(--secondary-color);
            border-color: var(--secondary-color);
            border-bottom: 2px solid white;
            margin-bottom: -2px;
            font-weight: 600;
        }
        
        .tab-content {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 0 8px 8px 8px;
            padding: 25px;
            margin-top: -1px;
        }
        
        /* Data table */
        .table-responsive {
            overflow-x: auto;
            margin: 20px 0;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: var(--bg-card);
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
            border-bottom: 2px solid #e9ecef;
        }
        
        .data-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        
        .data-table tr:hover {
            background: var(--bg-card);
        }
        
        .data-table .actions {
            display: flex;
            gap: 5px;
            white-space: nowrap;
        }
        
        /* Badges */
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-primary {
            background: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
        }
        
        .badge-danger {
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger-color);
        }
        
        .badge-success {
            background: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
        }
        
        .badge-secondary {
            background: var(--gray-light);
            color: var(--text-secondary);
        }
        
        .badge-info {
            background: rgba(52, 152, 219, 0.1);
            color: var(--secondary-color);
        }
        
        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 14px;
        }
        
        .btn-primary {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--accent-color);
        }
        
        .btn-secondary {
            background: var(--gray-dark);
            color: white;
        }
        
        .btn-secondary:hover {
            background: var(--gray-dark);
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: var(--text-primary);
        }
        
        .btn-warning:hover {
            background: var(--warning-color);
        }
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-danger:hover {
            background: var(--danger-color);
        }
        
        .btn-info {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn-info:hover {
            background: var(--accent-color);
        }
        
        /* Forms */
        .form-card {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--secondary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .form-group small {
            display: block;
            margin-top: 5px;
            color: var(--text-secondary);
            font-size: 12px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        /* User profile */
        .user-profile {
            background: var(--bg-card);
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .profile-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: var(--secondary-color);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
        }
        
        .profile-info h3 {
            margin: 0 0 5px 0;
            color: var(--text-primary);
        }
        
        .profile-username {
            margin: 0 0 10px 0;
            color: var(--text-secondary);
        }
        
        .profile-badges {
            display: flex;
            gap: 10px;
        }
        
        .user-info {
            background: var(--bg-card);
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .user-info h4 {
            margin-top: 0;
            color: var(--text-primary);
        }
        
        .user-info ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .user-info li {
            margin-bottom: 8px;
            color: var(--text-primary);
        }
        
        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
        }
        
        .empty-state i {
            margin-bottom: 20px;
            color: #dee2e6;
        }
        
        .empty-state h3 {
            margin: 0 0 10px 0;
            color: var(--text-primary);
        }
        
        .empty-state p {
            margin: 0 0 20px 0;
        }
        
        /* Summary */
        .summary {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
            color: #6c757d;
        }
        
        /* Password strength */
        .password-strength {
            margin-top: 10px;
        }
        
        .strength-bar {
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 5px;
        }
        
        .strength-fill {
            height: 100%;
            width: 0%;
            transition: width 0.3s, background-color 0.3s;
        }
        
        .strength-text {
            font-size: 12px;
            color: #6c757d;
        }
        
        .strength-text span {
            font-weight: 600;
        }
    </style>
</body>
</html>