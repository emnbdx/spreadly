<?php
  require 'loader.php';
  $end = new DateTime($_SERVER['EndDate']) < new DateTime('NOW');

  if(!$end) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      if(isset($_POST['to']) && isset($_POST['from']) && isset($_POST['message'])) {
        $repo->insertLove($_POST['to'], $_POST['from'], $_POST['message']);
  
        $_SESSION['success'] = true;
  
        header('Location:/');
        exit();
      } else {
        $_SESSION['error'] = true;
      }
    } else {
      $receivers = $repo->getReceivers();
    }
  }
?>

<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css" integrity="sha384-TX8t27EcRE3e/ihU7zmQxVncDAy5uIKz4rEkgIXeMed4M0jlfIDPvg6uqKI2xXr2" crossorigin="anonymous">

    <title>Spread the love</title>

    <style>
      html, body {
        height : 100%;
        font-size: 20px;
      }
      
      body {
        background: url(images/<?php echo random_int(1, 3) ?>.jpg) no-repeat center center fixed; 
        -webkit-background-size: cover;
        -moz-background-size: cover;
        -o-background-size: cover;
        background-size: cover;
      }

      .form-container  {
        background-color: white;
        padding: 2em;
        border-radius: 1em;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.5);
      }

      .github {
        float: right;
      }
    </style>
  </head>
  <body>
    <a href="https://github.com/emnbdx/spreadthelove" class="github">
      <img loading="lazy" width="149" height="149" src="https://github.blog/wp-content/uploads/2008/12/forkme_right_darkblue_121621.png?resize=149%2C149" class="attachment-full size-full" alt="Fork me on GitHub" data-recalc-dims="1">
    </a>

    <div class="container h-100">
      <div class="row h-100">
        <div class="form-container col-12 my-auto<?php echo $end ? " text-center" : "" ?>">
          <?php if(isset($_SESSION['success'])) { 
            unset($_SESSION['success']);
          ?>
            <div class="alert alert-success" role="alert">
              Merci à toi d'envoyer du love
            </div>
          <?php } ?>
          <?php if(isset($_SESSION['error'])) {
            unset($_SESSION['error']);
          ?>
            <div class="alert alert-danger" role="alert">
              Tous les champs sont obligatoires
            </div>
          <?php } ?>

          <?php if($end) { ?>
            <img src="/images/thats-all-folks.png" />
          <?php } else { ?>
            <form method="POST"> 
              <div class="form-group">
                <label for="to">A qui veux-tu envoyer du love ?</label>   
                <select id="to" name="to" class="form-control" required>
                    <option></option>
                    <?php foreach($receivers as $receiver) { ?>
                      <option value="<?php echo $receiver['id'] ?>"><?php echo $receiver['name'] ?></option>
                    <?php } ?>
                </select>
              </div>
              <div class="form-group">
                <label for="from">De la part de</label>   
                <input type="text" id="from" name="from" class="form-control" required/>
              </div>
              <div class="form-group">
                <label for="message">Ton message</label>   
                <textarea id="message" name="message" rows="5" class="form-control" required></textarea>
              </div>
              <div class="form-group">
                <input type="submit" value="Envoyer" class="btn btn-primary" />
              </div>
            </form>
          <?php } ?>
        </div>
      </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js" integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js" integrity="sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.min.js" integrity="sha384-w1Q4orYjBQndcko6MimVbzY0tgp4pWB4lZ7lr30WKz0vr/aWKhXdBNmNb5D92v7s" crossorigin="anonymous"></script>
  </body>
</html>