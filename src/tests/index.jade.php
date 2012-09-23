<!DOCTYPE html>

<html lang='pt-br'>
  <head>
    <link href='css/bootstrap.css' rel='stylesheet'>
    <link href='css/bootstrap-responsive.css' rel='stylesheet'>
    <link href='css/prettify.css' rel='stylesheet'>
    <!--
    <style>
       body{padding-top:120px;}
    </style>
    -->
  </head>
  <body>
    <!--.navbar.navbar-fixed-top-->
    <div class='navbar'>
      <div class='navbar-inner'>
        <div class='container'>
          <ul class='nav'>
            <?php foreach ($nav_list as $dropdown_name => $dropdown) { ?>
              <li class='dropdown'>
                <a data-toggle='dropdown' class='dropdown-toggle'>
                  <?php echo htmlspecialchars($dropdown_name) ?>
                  <span class='caret'>
                  </span>
                </a>
                <ul class='dropdown-menu'>
                  <?php foreach ($dropdown as $item) { ?>
                    <li>
                      <a href='<?php $__=isset($item->link) ? $item->link : $item['link'];echo '?test=' . $__ ?>'>
                        <?php 
                        $__=isset($item->name) ? $item->name : $item['name'];
                        echo htmlspecialchars($__)
                         ?>
                      </a>
                    </li>
                  <?php } ?>
                </ul>
              </li>
            <?php } ?>
          </ul>
        </div>
      </div>
    </div>
    <?php if($test) { ?>
      <div class='container'>
        <div class='row'>
          <div class='span6'>
            <ul class='nav nav-tabs'>
              <li class='active'>
                <a>
                  Jade
                </a>
              </li>
            </ul>
          </div>
        </div>
        <div class='row'>
          <div class='span6'>
<pre class='prettyprint linenums'><?php echo htmlspecialchars($jade) ?></pre>          </div>
        </div>
        <div class='row'>
          <div class='span6'>
            <ul class='nav nav-tabs'>
              <li class='active'>
                <a>
                  Tokens
                </a>
              </li>
            </ul>
          </div>
          <div class='span6'>
            <ul class='nav nav-tabs'>
              <li class='active'>
                <a>
                  Nodes
                </a>
              </li>
            </ul>
          </div>
        </div>
        <div class='row'>
          <div class='span6'>
<pre class='prettyprint linenums'><?php echo htmlspecialchars($tokens) ?></pre>          </div>
          <div class='span6'>
<pre class='prettyprint linenums'><?php echo htmlspecialchars($nodes) ?></pre>          </div>
        </div>
        <div class='row'>
          <div class='span6'>
            <ul class='nav nav-tabs'>
              <li class='active'>
                <a>
                  PHP
                </a>
              </li>
            </ul>
          </div>
          <div class='span6'>
            <ul class='nav nav-tabs'>
              <li class='active'>
                <a>
                  HTML
                </a>
              </li>
            </ul>
          </div>
        </div>
        <div class='row'>
          <div class='span6'>
<pre class='prettyprint linenums lang-php'><code><?php echo htmlspecialchars($php) ?></code></pre>          </div>
          <div class='span6'>
<pre class='prettyprint linenums lang-html'><code><?php echo htmlspecialchars($html) ?></code></pre>          </div>
        </div>
      </div>
    <?php } ?>
    <script src='js/jquery-1.7.2.min.js'>
    </script>
    <script src='js/bootstrap-dropdown.js'>
    </script>
    <script src='js/prettify.js'>
    </script>
    <script>
       jQuery(function($){
           $('.dropdown-toggle').dropdown();
           prettyPrint();
       });
    </script>
  </body>
</html>
