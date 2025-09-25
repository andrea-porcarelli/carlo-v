import App from "./app.js";
import Login from "./login.js";
import Homepage from "./controllers/homepage.js";
import Users from "./controllers/users.js";
import Crud from "./controllers/crud.js";
import Suppliers from "./controllers/suppliers.js";

const init = () => {
    App.init();
    Login.init();
    Homepage.init();
    Users.init();
    Crud.init();
    Suppliers.init();
}

$(function () {
    Dropzone.autoDiscover = false;
    init();
});
