<?php

  define("PATH_LOG", "studyhandler-log.csv");
  define("PAGE_ID", "StudyHandler Log (" . PATH_LOG . ")");
  
  include "_password_protect_studyhandler.php";
  include "topstudyhandler.php";
  putenv("TZ=US/Eastern");
  
  echo "<h2>StudyHandler Log</h2><center>(" . PATH_LOG . ")<br><em>Today's date: " . date("n/j/Y") . "</em></center><p>";

  $fullLog = file_get_contents(PATH_LOG);
  echo nl2br($fullLog);
  include "bottomstudyhandler.php";
?>
