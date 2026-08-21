#### *this file show how i strat the project from zero to end


## Step One - File Structure

```txt
Project Root : 

    -public : 
        -admin :
            -assets :
                js : 
                ....
                css :
                ....

            -components :
                navbar.php
                sidebar.php
                template.php 

            -pages :
                employees.php # Show , add , edit , remove an employee (By Owner only)
                menu.php # Add foods , remove foods , featured foods , menu listing
                discounts.php # Add discount for category , specific plate , 
                tables.php # Add tables in the menu with number & floor number , edit / move place of table & Generate qr code and print it 
                orders.php # see the current orders and its status (if its complited will not be shown here its will be shown in the log , if its underwork cannot be canceled)
                log.php # see all edits Happended by any empoly 
                dashboard.php # see profits , status , total orders last 24hr / 7 days orders & more 
                logout.php # Remove session
                login.php # login page 

        -client :
            -assets :
                js : 
                ....
                css :
                ....

            -components :
                navbar.php
                sidebar.php
                template.php 

            -pages :
                landing.php # Hero-img then menu with dropdown to select the category 
                order.php # show user order with comfiramtion button 
                food.php # show the food to select the qty & if there user note & select the options that added bu admin (ex : with BBQ sauce)
                view.php # here if user confirm the order will see the staus of the order if its not underwork/complited so user can edit/cancel the order 

    -api : 
        
        -Controllers : 
            FoodController.php 
            CategoriesController.php 
            TablesController.php 
            OrdersController.php 
            DiscountsController.php 
            LogController.php 
            AuthController.php
            EmployeeController.php

        -Validators
            FoodValidator.php 
            CategoriesValidator.php 
            TablesValidator.php 
            OrdersValidator.php 
            DiscountsValidator.php 
            LogValidator.php 
            EmployeeValidator.php
            AuthValidator.php

        -Models :
            FoodModel.php
            CategoriesModel.php
            TablesModel.php
            OrdersModel.php
            DiscountsModel.php
            EmployeeModel.php
            LogModel.php

        -Middleware :
            AuthMiddleware.php
            RoleMiddleware.php 
    
    -config : 
        database.php
        app.php
        permissions.php

    -database :
        schema.sql
        seed.sql

    -storage :
        -logs
        -images
            -employees 
            -foods
            -uploads

    -routes :
        web.php
        api.php

    .env
    .env.example
    .gitignore
    .htaccess
```




## STEP TWO - Client front-end 
### Create Clinet front-end with HTML & CSS , Js only (No Backend)
