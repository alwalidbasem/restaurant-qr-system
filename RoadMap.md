## In the first i plan to create this project form zero 


#### so i create this files structure : 

```txt
Project Root : 

    -public : 
        -admin :
            -assets :
                js : 
                ....
                css :
                ....
                images :
                    -employees: ...

            -components :
                navbar.php
                sidebar.php
                template.php 

            -pages :
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
                images :
                    -foods: ...

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
            -foods : 
                foods.php 
                validation.php
            -categories : 
                categories.php 
                validation.php
            -tables : 
                tables.php 
                validation.php
            -orders : 
                orders.php 
                validation.php
            -discounts : 
                discounts.php 
                validation.php
            -log : 
                log.php 
        
        -Models :
            foods.php
            categories.php
            tables.php
            orders.php
            discounts.php
            log.php

        -middleware :
            auth.php
            roles.php 
```



