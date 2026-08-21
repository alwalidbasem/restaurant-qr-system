<!DOCTYPE html>
<html lang="<?= htmlspecialchars((string) ($site_lang ?? 'en'), ENT_QUOTES); ?>" dir="<?= htmlspecialchars((string) ($site_dir ?? 'ltr'), ENT_QUOTES); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <?php 

        if (isset($template['header']['title'])) echo "<title>".$template['header']['title']."</title>";
        if (isset($template['header']['meta']['description'])) echo '<meta name="description" content="'.$template['header']['meta']['description'].'">';
        
        if(isset($template['header']['body'])){
            echo $template['header']['body'];
        }

    ?>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,300;0,9..144,500;0,9..144,600;0,9..144,700;1,9..144,500&family=Manrope:wght@400;500;600;700;800&family=Noto+Kufi+Arabic:wght@500;600;700;800&family=Noto+Sans+Arabic:wght@400;500;600;700;800&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>



    <body>
        <?php
            if(isset($template['body'])) {
                echo $template['body'];
            }else{
                exit(400);
            }
        ?>
    </body>

        <?php
            if(isset($template['footer'])) {
                echo $template['footer'];
            }
        ?>

</html>
