@if(session('ok'))
    <div class="alert alert-success" role="alert">
        {{ session('ok') }}
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger" role="alert">
        <ul style="margin:0; padding-inline-start:1.2rem;">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('.alert').forEach(function(el){
    setTimeout(function(){ el.style.display='none'; }, 4000);
  });
});
</script>

