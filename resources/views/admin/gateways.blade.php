<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayBridge - Payment Gateway Settings</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-light: #eef2ff;
            --success: #10b981;
            --success-light: #ecfdf5;
            --warning: #f59e0b;
            --warning-light: #fffbeb;
            --danger: #ef4444;
            --bg: #0f172a;
            --card-bg: #1e293b;
            --card-border: #334155;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --input-bg: #0f172a;
            --input-border: #334155;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: var(--bg);
            color: var(--text-main);
            min-height: 100vh;
            padding: 40px 20px;
        }

        .container {
            max-width: 1280px;
            margin: 0 auto;
        }

        /* Header */
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 35px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-title-area {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .brand-badge {
            background: linear-gradient(135deg, #6366f1, #a855f7);
            color: white;
            font-weight: 800;
            font-size: 20px;
            padding: 10px 16px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            letter-spacing: -0.5px;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: -0.5px;
        }

        .header p {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 4px;
        }

        /* Alert */
        .alert {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        /* Tabs */
        .category-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            overflow-x: auto;
            padding-bottom: 5px;
        }

        .tab-btn {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            color: var(--text-muted);
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
        }

        .tab-btn:hover {
            color: #ffffff;
            border-color: #475569;
        }

        .tab-btn.active {
            background: var(--primary);
            color: #ffffff;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
        }

        /* Gateway Cards Grid */
        .gateways-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 24px;
        }

        .gateway-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
        }

        .gateway-card:hover {
            border-color: #475569;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .card-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .gateway-info h3 {
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .gateway-category {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--primary);
            background: rgba(99, 102, 241, 0.15);
            padding: 3px 8px;
            border-radius: 6px;
            display: inline-block;
            margin-top: 6px;
        }

        .gateway-desc {
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 10px;
            line-height: 1.5;
            min-height: 40px;
        }

        /* Badges */
        .badges-row {
            display: flex;
            gap: 8px;
            margin: 16px 0;
            flex-wrap: wrap;
        }

        .badge {
            font-size: 12px;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 20px;
        }

        .badge-active {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .badge-inactive {
            background: rgba(148, 163, 184, 0.1);
            color: #94a3b8;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        .badge-sandbox {
            background: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .badge-live {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .badge-default {
            background: rgba(168, 85, 247, 0.15);
            color: #c084fc;
            border: 1px solid rgba(168, 85, 247, 0.3);
        }

        /* Action Buttons */
        .card-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 16px;
            border-top: 1px solid var(--card-border);
            margin-top: 10px;
        }

        .btn-configure {
            background: var(--primary);
            color: white;
            border: none;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-configure:hover {
            background: var(--primary-hover);
        }

        /* Toggle Switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 44px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #334155;
            transition: .3s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--success);
        }

        input:checked + .slider:before {
            transform: translateX(20px);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 30px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            animation: modalFadeIn 0.25s ease-out;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.96); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--card-border);
        }

        .modal-header h2 {
            font-size: 20px;
            font-weight: 700;
        }

        .btn-close {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 24px;
            cursor: pointer;
            line-height: 1;
        }

        .btn-close:hover {
            color: #ffffff;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #cbd5e1;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .form-control {
            width: 100%;
            background: var(--input-bg);
            border: 1px solid var(--input-border);
            border-radius: 10px;
            padding: 12px 14px;
            color: #ffffff;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s;
        }

        .form-control:focus {
            border-color: var(--primary);
        }

        textarea.form-control {
            min-height: 90px;
            resize: vertical;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 13px;
            padding: 4px;
        }

        .toggle-password:hover {
            color: #ffffff;
        }

        .switches-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            background: rgba(15, 23, 42, 0.6);
            padding: 16px;
            border-radius: 12px;
            border: 1px solid var(--card-border);
            margin-bottom: 24px;
        }

        .switch-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
            font-weight: 600;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 20px;
            border-top: 1px solid var(--card-border);
        }

        .btn-save {
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-save:hover {
            background: var(--primary-hover);
        }

        .btn-cancel {
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--card-border);
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-cancel:hover {
            color: #ffffff;
            border-color: #64748b;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="header-title-area">
            <div class="brand-badge">PayBridge</div>
            <div>
                <h1>Payment Gateway Settings</h1>
                <p>Manage API credentials, test modes, and activate gateways without touching code.</p>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert">
            <span>✅ {{ session('success') }}</span>
            <span style="cursor:pointer;" onclick="this.parentElement.remove()">✕</span>
        </div>
    @endif

    <!-- Category Tabs -->
    <div class="category-tabs">
        <button class="tab-btn active" onclick="filterCategory('all', this)">All Gateways ({{ $gateways->count() }})</button>
        <button class="tab-btn" onclick="filterCategory('MFS', this)">Mobile Banking (MFS)</button>
        <button class="tab-btn" onclick="filterCategory('Crypto', this)">Cryptocurrency</button>
        <button class="tab-btn" onclick="filterCategory('International Cards', this)">International Cards</button>
        <button class="tab-btn" onclick="filterCategory('Aggregators', this)">Aggregators</button>
        <button class="tab-btn" onclick="filterCategory('Banks & QR', this)">Banks & QR</button>
    </div>

    <!-- Gateways Grid -->
    <div class="gateways-grid">
        @foreach($gateways as $gateway)
            @php
                $definition = $definitions[$gateway->code] ?? [];
                $category = $definition['category'] ?? $gateway->category;
            @endphp
            <div class="gateway-card" data-category="{{ $category }}">
                <div>
                    <div class="card-top">
                        <div class="gateway-info">
                            <h3>{{ $gateway->name }}</h3>
                            <span class="gateway-category">{{ $category }}</span>
                        </div>
                        <form action="{{ route('paybridge.admin.toggle', $gateway->code) }}" method="POST">
                            @csrf
                            <label class="switch" title="Toggle Active">
                                <input type="checkbox" onchange="this.form.submit()" {{ $gateway->is_active ? 'checked' : '' }}>
                                <span class="slider"></span>
                            </label>
                        </form>
                    </div>

                    <div class="gateway-desc">
                        {{ $definition['description'] ?? 'Configure credentials to accept payments.' }}
                    </div>

                    <div class="badges-row">
                        <span class="badge {{ $gateway->is_active ? 'badge-active' : 'badge-inactive' }}">
                            {{ $gateway->is_active ? '● Active' : '○ Inactive' }}
                        </span>

                        <span class="badge {{ $gateway->is_sandbox ? 'badge-sandbox' : 'badge-live' }}">
                            {{ $gateway->is_sandbox ? 'Sandbox (Test)' : 'Live Production' }}
                        </span>

                        @if($gateway->is_default)
                            <span class="badge badge-default">★ Default Gateway</span>
                        @endif
                    </div>
                </div>

                <div class="card-actions">
                    @if(!$gateway->is_default)
                        <form action="{{ route('paybridge.admin.default', $gateway->code) }}" method="POST" style="margin:0;">
                            @csrf
                            <button type="submit" class="btn-cancel" style="padding: 7px 12px; font-size: 12px;">Make Default</button>
                        </form>
                    @else
                        <form action="{{ route('paybridge.admin.default', $gateway->code) }}" method="POST" style="margin:0;">@csrf<button type="submit" class="btn-cancel" style="padding: 7px 12px; font-size: 12px; color: var(--primary);" title="Click to unset default">Primary Default ✕</button></form>
                    @endif

                    <button type="button" class="btn-configure" onclick="openModal('modal-{{ $gateway->code }}')">
                        Configure
                    </button>
                </div>
            </div>

            <!-- Configuration Modal -->
            <div id="modal-{{ $gateway->code }}" class="modal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Configure {{ $gateway->name }}</h2>
                        <button type="button" class="btn-close" onclick="closeModal('modal-{{ $gateway->code }}')">&times;</button>
                    </div>

                    <form action="{{ route('paybridge.admin.update', $gateway->code) }}" method="POST">
                        @csrf

                        <div class="switches-grid">
                            <div class="switch-label">
                                <span>Enable Gateway</span>
                                <label class="switch">
                                    <input type="checkbox" name="is_active" value="1" {{ $gateway->is_active ? 'checked' : '' }}>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="switch-label">
                                <span>Sandbox Mode</span>
                                <label class="switch">
                                    <input type="checkbox" name="is_sandbox" value="1" {{ $gateway->is_sandbox ? 'checked' : '' }}>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>

                        @php
                            $fields = $definition['fields'] ?? [];
                            $savedCredentials = $gateway->credentials ?? [];
                        @endphp

                        @foreach($fields as $fieldName => $fieldMeta)
                            <div class="form-group">
                                <label>{{ $fieldMeta['label'] }}</label>
                                <div class="input-wrapper">
                                    @if(($fieldMeta['type'] ?? 'text') === 'textarea')
                                        <textarea 
                                            name="credentials[{{ $fieldName }}]" 
                                            class="form-control" 
                                            placeholder="{{ $fieldMeta['placeholder'] ?? ('Paste ' . $fieldMeta['label'] . ' here...') }}"
                                        >{{ $savedCredentials[$fieldName] ?? '' }}</textarea>
                                    @else
                                        <input 
                                            type="{{ $fieldMeta['type'] ?? 'text' }}" 
                                            id="input-{{ $gateway->code }}-{{ $fieldName }}"
                                            name="credentials[{{ $fieldName }}]" 
                                            value="{{ $savedCredentials[$fieldName] ?? ($fieldMeta['default'] ?? '') }}" 
                                            class="form-control" 
                                            placeholder="{{ $fieldMeta['placeholder'] ?? ('Enter ' . $fieldMeta['label']) }}"
                                        >
                                        @if(($fieldMeta['type'] ?? 'text') === 'password')
                                            <button 
                                                type="button" 
                                                class="toggle-password" 
                                                onclick="toggleVisibility('input-{{ $gateway->code }}-{{ $fieldName }}')"
                                            >
                                                👁️
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        <div class="modal-footer">
                            <button type="button" class="btn-cancel" onclick="closeModal('modal-{{ $gateway->code }}')">Cancel</button>
                            <button type="submit" class="btn-save">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
    function filterCategory(category, btn) {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const cards = document.querySelectorAll('.gateway-card');
        cards.forEach(card => {
            const cardCat = card.getAttribute('data-category') || '';
            if (category === 'all' || cardCat === category || cardCat.includes(category) || category.includes(cardCat)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    function openModal(id) {
        document.getElementById(id).classList.add('active');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    function toggleVisibility(inputId) {
        const input = document.getElementById(inputId);
        if (input.type === "password") {
            input.type = "text";
        } else {
            input.type = "password";
        }
    }

    // Close modal when clicking outside of modal content
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('active');
        }
    }
</script>

</body>
</html>
