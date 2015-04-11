<?php

  define("PATH_LOG_QS", "../qsethandler-log.csv");
  define("PAGE_ID", "QSetHandler Log (" . PATH_LOG_QS . ")");
  
  include "_password_protect_studyhandler.php";
  include "topstudyhandler.php";
  putenv("TZ=US/Eastern");
  
  echo "<h2>QSetHandler Log</h2><center>(" . PATH_LOG_QS . ")<br><em>Today's date: " . date("n/j/Y") . "</em></center><p>";

  $fullLog = file_get_contents(PATH_LOG_QS);
  echo nl2br($fullLog);
  include "bottomstudyhandler.php";
?>
