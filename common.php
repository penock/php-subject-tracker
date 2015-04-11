<?php

  /****** OPTIONS ******/
  define("SIMULATE_DATE", false); // Should be false for actual running (but can be used to make up a date where the job wasn't run for some reason). Can set this to any date e.g. "8/20/2010" to simulate actions that will be taken on that date. Should only be used offline, so you don't send out bogus emails.


  /****** FOR STUDYHANDLER AND QSETHANDLER ******/
  define("FILENAME_DATA", "studyhandler-data.csv");
  define("FILENAME_LOCK_STUDYHANDLER", "studyhandler-lockfile");
  define("FILENAME_STUDYHANDLER_MESSAGES", "studyhandler-messages-editable.txt");
  define("PLINE_NAME", 0); // spec for P lines in data csv... for whatever reasons, I didn't use classes for PLines and Actions; I used arrays
  define("PLINE_EMAIL", 1);
  define("PLINE_STARTDATE", 2);
  define("PLINE_TRACK", 3);
  define("PLINE_DONESTRING", 4);
  define("PLINE_STATUS", 5); // note, whenever dealing with someone's status, use myTrim() when you read it to eliminate \r\n, and add that back when you save the dataLine
  define("ACTION_DATE", 0); // target date
  define("ACTION_STRING", 1);
  define("ACTION_P_NAME", 2);
  define("ACTION_P_EMAIL", 3);
  define("ACTION_P_STARTDATE", 4);
  define("ACTION_P_TRACK", 5);
  
  
  /* myToday() is the anchor date for all operations... 
     Should be either the real date or a date you're using for testing.
     There should be no references to date("n/j/Y") anywhere else in the code except when it's used for formatting a date-mathed date
  */
  function myToday() {
    if(SIMULATE_DATE)
      return SIMULATE_DATE;
    else
      return date("n/j/Y");
  }
  
    /* myTrim() removes any LF/LN, CR, CRLF, spaces or tabs from beginning or end
   * of string. It does redundant stuff, because acoording to bit.ly/4XZjXa
   * trim() won't normally remove CRLFs. */
  function myTrim($string) {
    $string = trim($string);
    // Remove any one terminating LF/LN, CR, or CRLF break
    if(substr($string, -1) == "\n") $string = substr($string, 0, -1);
    if(substr($string, -1) == "\r") $string = substr($string, 0, -1);
    $string = trim($string);
    return $string;
  }

  /* msgLinesGetLineAfter() finds string in msgFileLines line array, returns line after that line */
  function msgLinesGetLineAfter($msgFileLines, $needleStem) {
    foreach($msgFileLines as $key => $line) {
      if(substr_count($line, "[[$needleStem]]"))
        return myTrim($msgFileLines[$key + 1]);
    }
    myExit("error", "Messages file error #1: Could not find not find '$needleStem' in " . PATH_STUDYHANDLER_MESSAGES);
  }

  /* msgLinesGetRange() finds string in msgFileLines line array, returns string of lines between -START and -END */  
  function msgLinesGetRange($msgFileLines, $needleStem) {
    foreach($msgFileLines as $key => $line) {
      if(substr_count($line, "[[$needleStem-START]]"))
        $startLineNum = $key + 1;
      if(substr_count($line, "[[$needleStem-END]]"))
        $endLineNum = $key - 1;
    }
    if(isset($startLineNum) && isset($endLineNum))
      return myTrim(implode("", array_slice($msgFileLines, $startLineNum, $endLineNum - $startLineNum + 1)));
    else
      myExit("error", "Messages file error #2: Could not find not find '$needleStem' in " . PATH_STUDYHANDLER_MESSAGES);
  }

   /* Grabs subject and msg message from $msgFileLines
   * (which must be file(studyhandler-messages-editable.txt) according to 
   * your parseTarget, which is like "WEEK0" or "ADD-X", and sends it
   * to the given P.
   */
  function mailPMessage($dueAction, $parseTarget, $msgFileLines) { 
    if($dueAction[ACTION_P_TRACK] == "m") $dueAction[ACTION_P_TRACK] = "mn";
    if($dueAction[ACTION_P_TRACK] == "o") $dueAction[ACTION_P_TRACK] = "on";
		$parseTarget = str_replace("-X", "-" . strtoupper($dueAction[ACTION_P_TRACK]), $parseTarget);
    $subj = msgLinesGetLineAfter($msgFileLines, $parseTarget . "-SUBJECT");
    $msg = msgLinesGetRange($msgFileLines, $parseTarget . "-MESSAGE") . "\n\n" . msgLinesGetRange($msgFileLines, "SIGNATURE");
    $msg = msgLinesGetRange($msgFileLines, "GREETING") . "\n\n$msg";
    if(substr_count($dueAction[ACTION_STRING], "remind")) {
      $subj = "REMINDER: " . $subj;
      if(substr_count($dueAction[ACTION_STRING], "ir-"))
        $msg = msgLinesGetRange($msgFileLines, "REMINDERINSTRUCTIONS-PREFIX") . "\n$msg";
      else
        $msg = msgLinesGetRange($msgFileLines, "REMINDER-PREFIX") . "\n$msg";
    }
    $msg = str_replace("P_NAME", $dueAction[ACTION_P_NAME], $msg);
    $msg = str_replace("P_EMAIL", $dueAction[ACTION_P_EMAIL], $msg);
    if(isset($_POST["P_ID"]))
      $msg = str_replace("P_ID", $_POST["P_ID"], $msg);
    else
      $msg = str_replace("P_ID", "[see original email for your login/ID]", $msg);
    $msg = str_replace("P_WEEK", substr($dueAction[ACTION_STRING], strpos($dueAction[ACTION_STRING], "-") - 1, 1), $msg);
    $msg = str_replace("P_ACTIONTAG", substr($dueAction[ACTION_STRING], 0, strpos($dueAction[ACTION_STRING], "-")), $msg);
    $msg = str_replace("TODAY_DAYOFWEEK_MONTH_DAY", date("l, F j", strtotime(myToday())), $msg);
    $subj = str_replace("TODAY_DAYOFWEEK_MONTH_DAY", date("l, F j", strtotime(myToday())), $subj);
    
    $email = $dueAction[ACTION_P_EMAIL];
    myMail($dueAction[ACTION_P_EMAIL], $subj, $msg);
  }

?>
