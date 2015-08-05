<?php
	require('database.php');
	require('mail.php');
	
	$user = Database::get_user_by_id($_SESSION['id']);
	$next_to_last_login = Database::get_next_to_last_login_of_user($_SESSION['id']);
?>

<!DOCTYPE html>
<html>
<? require('html-include/head.html'); ?>
    <body>
    	<nav id="head-nav" class="navbar">
    		<div class="navbar-inner content-width">
    			<a href="#home">
    				<img class="logo" src="img/logo-46.png" />
    			</a><br class="clear-both smaller-800">
    			<ul class="nav left">
    				<li id="nav_home" class="nav-img-li">
                        <a href="#home"><img src="img/home.png" class="nav-image" alt="Home" title="Home"/></a>
                    </li>
    				<li id="nav_query"><a href="#query">Query</a></li>
    				<li id="nav_word-lists"><a href="#word-lists">Word lists</a></li>
    				<li id="nav_share"><a href="#share">Share</a></li>
    				<li id="nav_user"><a href="#user">User</a></li>
    			</ul>
    			<ul class="nav right">
    				<li id="nav_settings" class="nav-img-li">
                        <a href="#settings"><img src="img/settings.png" class="nav-image" alt="Settings" title="Settings"/></a>
                    </li>
    				<li id="nav_logout" class="nav-img-li">
                        <a href="/./logout.php"><img src="img/logout.png" class="nav-image" alt="Logout" title="Logout"/></a>
                    </li>
    			</ul><br class="clear-both">
    		</div>
    	</nav>
    	
    	<div class="main content-width" id="main">
    		<div id="content-home">
    			<div class="left-column">
    				<div class="box">
    					<div class="box-head">
    						Hey <? echo $user->firstname; ?>!
    					</div>
    					<div class="box-body">
    						<p>Last login at <? echo $next_to_last_login->get_date_string(); ?> from IP-address <? echo $next_to_last_login->ip; ?></p>
    					</div>
    				</div>
    			</div>
    			<div class="right-column">
    				<div class="box">
    					<div class="box-head">
    						Recently used
    					</div>
    					<div class="box-body">
    						coming soon...
    					</div>
    				</div>
    				
    			</div>
    		</div>
    		
    		
    		<div id="content-query">
    			
    		</div>
    		
    		
    		<div id="content-word-lists">
    			
    		</div>
    		
    		
    		<div id="content-share">
    			
    		</div>
    		
    		
    		<div id="content-user">
    			<div class="left-column">
    				<div class="box">
    					<div class="box-head">
    						People you've added
    					</div>
    					<div class="box-body">
    						<?php
    							/*$added_users = Database::get_list_of_added_users_of_user($_SESSION['id']);
    							$number_of_added_users = array_count_values($added_users);
    							for ($i = 0; $i < $number_of_added_users; $i ++) { 
									
								}*/
    						?>
    					</div>
    				</div>
    			</div>
    			<div class="right-column">
    				
    			</div>
    		</div>
    		
    		
    		<div id="content-settings">
    			
    		</div>
    		
    		<br class="clear-both hide-below-700">
        </div>
        
        <?php
        	require('html-include/footer.html');
        ?>
        
        
        <!-- jquery -->
        <script src="jquery-1.11.3.min.js" type="text/javascript"></script>
        <script src="single-page-app.js" type="text/javascript"></script>
        
        <?php 
        	require('html-include/scripts.html');
		?>
    </body>
</html>
