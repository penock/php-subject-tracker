<?php
		
	$is_logged_in = false;
	if (isset($dailyRun))
	{
	  if ($dailyRun == "yourpasswordhere")
	    $is_logged_in = true;
	}
	
	if (isset($_COOKIE["manage"]) && $_COOKIE["manage"] == "yourpasswordhere")
	{
		$is_logged_in = true;
	}
	
	if (isset($_POST["manage_code"]))
	{
		$code = $_POST["manage_code"];

		if ($code == "yourpasswordhere") 
		{
			setcookie("manage", $code, time() + (60 * 60 * 24 * 14)); // last number (14) represents days
			$is_logged_in = true;
		}
	}

	if (!$is_logged_in)
	{
		?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN"
   "http://www.w3.org/TR/html4/strict.dtd">

<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>_password_protect_studyhandler</title>
	<!-- Date: 8/17/2010 -->
</head>
<body>
	<form method="POST">
	Please enter the password for StudyHandler: <input name="manage_code" type="text" /> <input type="submit" />
	</form>
</body>
</html>

<?php
		die;
	}
?>
