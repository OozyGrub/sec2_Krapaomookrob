@extends('layouts.app')

ิ@section('title', 'My Shopping Cart')

@section('topic', 'Cart')

@section('menu')

    <a href="#" class="btn ownbtn px-5">Back</a>

@endsection

@section('content')
    {{-- content of cart --}}
    
    <div class="card pb-2">
        <div class="card-body">
            <h4 class="card-title">Courses in Cart</h4>
            <div class="row mt-3 mb-2 bline">
                <div class="col-lg">Tutor</div>
                <div class="col-lg">Available Subjects</div>
                <div class="col-lg">Areas</div>
                <div class="col-lg">Classes</div>
                <div class="col-lg">Price/Start Date</div>
                <div class="col-lg"></div>
            </div>
            
            <div id='app'>
                <cart-item button-type="cancelbtn"></cart-item>
            </div>
        </div>
        
    </div>

@endsection
    
<script>
    // declare delete cart function here
function checkOutCart() {
    
    Vue.cookie.delete("cart");
}
    
</script>

