<div class="toast-stack" id="toast-stack" aria-live="polite">
    @if (session('success') && ! ($ignorarSessao ?? false))
        <div class="toast toast--success" role="status">
            <span class="toast__texto">{{ session('success') }}</span>
            <button type="button" class="toast__fechar" aria-label="Fechar">&times;</button>
        </div>
    @endif
    @if (session('error') && ! ($ignorarSessao ?? false))
        <div class="toast toast--error" role="alert">
            <span class="toast__texto">{{ session('error') }}</span>
            <button type="button" class="toast__fechar" aria-label="Fechar">&times;</button>
        </div>
    @endif
    @if (session('warning') && ! ($ignorarSessao ?? false))
        <div class="toast toast--warning" role="alert">
            <span class="toast__texto">{{ session('warning') }}</span>
            <button type="button" class="toast__fechar" aria-label="Fechar">&times;</button>
        </div>
    @endif
    @if (isset($errors) && $errors->any() && ! ($ignorarErros ?? false))
        <div class="toast toast--error" role="alert">
            <span class="toast__texto">
                @foreach ($errors->all() as $erro)
                    <span class="toast__linha">{{ $erro }}</span>
                @endforeach
            </span>
            <button type="button" class="toast__fechar" aria-label="Fechar">&times;</button>
        </div>
    @endif
</div>
<script>
(function () {
    function stack() {
        var el = document.getElementById('toast-stack');
        if (!el) {
            el = document.createElement('div');
            el.id = 'toast-stack';
            el.className = 'toast-stack';
            el.setAttribute('aria-live', 'polite');
        }
        if (el.parentNode !== document.body) {
            document.body.appendChild(el);
        }
        return el;
    }

    function fechar(toast) {
        if (!toast || !toast.parentNode) return;
        toast.style.transition = 'opacity 0.3s';
        toast.style.opacity = '0';
        setTimeout(function () { toast.remove(); }, 300);
    }

    function agendar(toast) {
        var ms = toast.classList.contains('toast--error') ? 6000 : 4500;
        setTimeout(function () { fechar(toast); }, ms);
    }

    function ligarFechar(toast) {
        var btn = toast.querySelector('.toast__fechar');
        if (!btn || btn.dataset.ligado) return;
        btn.dataset.ligado = '1';
        btn.addEventListener('click', function () { fechar(toast); });
    }

    window.mostrarToast = function (mensagem, tipo) {
        var texto = String(mensagem || '').trim();
        if (!texto) return;
        tipo = tipo || 'success';
        var root = stack();
        if (tipo === 'error') {
            root.querySelectorAll('.toast--error').forEach(function (t) { t.remove(); });
        }
        var toast = document.createElement('div');
        toast.className = 'toast toast--' + tipo;
        toast.setAttribute('role', tipo === 'success' ? 'status' : 'alert');
        var span = document.createElement('span');
        span.className = 'toast__texto';
        span.textContent = texto;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'toast__fechar';
        btn.setAttribute('aria-label', 'Fechar');
        btn.textContent = '\u00d7';
        toast.appendChild(span);
        toast.appendChild(btn);
        root.appendChild(toast);
        ligarFechar(toast);
        agendar(toast);
    };

    stack().querySelectorAll('.toast').forEach(function (toast) {
        ligarFechar(toast);
        agendar(toast);
    });
})();
</script>
