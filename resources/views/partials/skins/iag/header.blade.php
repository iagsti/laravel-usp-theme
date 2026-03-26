@section('skin_styles')
@parent {{-- devemos incluir o conteúdo existente --}}
<style>
    /* #skin_header é o div pai */
    #skin_header {
        background-color: #041650;
    }

    #skin_header  .container-fluid {
        display: block;
        height: 70px;
        font-size: 20px;
    }

    #skin_header .skin_logo img {
        height: 50px;
        margin: 10px;
    }

    #skin_header .skin_texto img {
        margin-top: 8px;
        height: 50px;
    }

</style>
@endsection

@section('skin_header')
<!-- container vai ocultar em mobile para ganhar espaço -->
<nav class="navbar bg-body-tertiary">
  <div class="container-fluid">
    <a class="navbar-brand text-white align-items-center d-flex" href="{{ config('app.url') }}">
      <img src="{{ asset('/vendor/laravel-usp-theme/skins/iag/images/logo_iag.png') }}" alt="Logo do IAG" class="d-inline-block align-text-top" style="height: 50px;">
      <span class="ml-2">{{ config('app.name') }}</span>
    </a>
  </div>
</nav>
@endsection
