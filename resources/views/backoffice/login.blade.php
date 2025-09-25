<!DOCTYPE html>
<html lang="en">
@include('backoffice.components.header', ['title' => 'Login'])
<body class="pace-done background-light">
    <div class="middle-box text-center loginscreen animated fadeInDown">
        <div>
            <div>
                <h1 class="logo-name" style="font-size: 34px">
                    CARLO V
                </h1>
            </div>
            <p>Log-in</p>
            <form class="m-t form-login" role="form">
                <div class="form-group">
                    <input type="email" class="form-control" placeholder="Username" required="" name="email">
                    <span class="invalid-feedback"></span>
                </div>
                <div class="form-group">
                    <input type="password" class="form-control" placeholder="Password" required="" name="password">
                </div>
                <button type="button" class="btn btn-primary block full-width m-b btn-login">Login</button>
                <div class="col-xs-12">
                    <div class="response-login"></div>
                </div>
            </form>
        </div>
    </div>
    @include('backoffice.components.footer')
</body>
</html>
