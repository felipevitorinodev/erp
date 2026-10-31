@php($tituloVer = trim($__env->yieldContent('page_title')))
<div class="ver-fragment" data-titulo="{{ $tituloVer }}">
    <div class="ver-fragment__acoes">
        @yield('page_actions')
    </div>
    @yield('content')
</div>
@stack('modals')
@stack('scripts')
