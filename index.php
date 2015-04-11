<?php
  /****** TO CLEAN UP BEFORE LAUNCH *****
   * - Set SIMULATE_DATE (in common.php) and SIMULATE_EMAILS to false (also in qs.php)
   */ 

  /****** OPTIONS ******/
  define("OPTION_ECHO_LOGGING", true);
  define("SIMULATE_EMAILS", true);

  /****** CONSTANTS ******/
  include "common.php";
  define("ADMIN_EMAIL", "handheldtrainingstudy@gmail.com");
  define("DEBUG_EMAIL", "p.enock@gmail.com");
  define("PAGE_ID", "Phil's S t u d y Handler 1.1, written in PHP, 2010");
  define("PATH_LOG", "studyhandler-log.csv");
  define("PATH_LOG_BAK_LASTRUN", "studyhandler-log-backup-beforelastrun.csv");
  define("LENGTH_OF_TRAINING", 28);
  define("ERROR_TELL_US", " Ask Phil what to do.");
  define("BACKUP_LOG_PROBABILITY", .25);
  define("PATH_DATA", FILENAME_DATA);
  define("PATH_LOCK_STUDYHANDLER", FILENAME_LOCK_STUDYHANDLER);
  define("PATH_STUDYHANDLER_MESSAGES", FILENAME_STUDYHANDLER_MESSAGES);

  // include "_password_protect_studyhandler.php";
  include "topstudyhandler.php";
  putenv("TZ=US/Eastern");

  /***** GLOBAL ARRAYS *****/
  /* ** Prescribed actions (emails), mapped out: ** 
    - Array elements are "actionTag" => day # to send it on
    - "remind" actions occur 1 and 2 days after initial "send" action
    - "warnus" actions occur 3 days after initial "send" action
    - "howgoingus" action occurs 1 day after "d1-warnus" only
    - 1 means day 1, the P's start date. The day1 -send action is done by addP and never in getDueActionStrings (see note1)... it's here so that reminder and warnus go out
    - Daily reminders aren't in this list, they're just inserted happen in getDueActionStrings()
    - actionTag can be any length, don't have to be 2 characters, but they must be non-overlappable... like you can't have coexisting "et" and "tw" and w1" because then etw1 would match all 3 */
  $actionTags = array(
    "d1" => 1, "p0" => 1, // For Now Ps, d1 (specifically d1b) IS their w0. d1a sends them to keysurvey Day1 Questions qst, which returns d1a to add to donestring and sends them to weekly with keysurvey url do=d1b
    "p1" => 8, "p2" => 15, "p3" => 22, "p4" => 29,
		"w1" => 8, "w2" => 15, "w3" => 22, "w4" => 29,
    "etc" => 29,
    "f1" => 58, "f2" => 88);
    
  /* Also actions:
    1. pre-instruction reminders 1-3 days after instruction email is sent to (online-only), at 3 days it's warnus
    2. endoftraining dropped reminders 1-3 days after fulldrop email is sent, at 3 days it's warnus */

  $parseList = array(
    "daily" => "DAILY-X",
    "lastday" => "LASTTRAININGDAY",
    "ir" => "ADD-ON",
    "dir" => "ENDOFDELAY",
    "d1" => "DAY1", "p0" => "WEEKLY", 
    "p1" => "WEEKLY", "p2" => "WEEKLY", "p3" => "WEEKLY", "p4" => "WEEK4",
    "w1" => "WEEKLY", "w2" => "WEEKLY", "w3" => "WEEKLY", "w4" => "WEEK4",
    "f1" => "FOLLOWUP", "f2" => "FOLLOWUP2",
    "etc" => "ENDOFTRAINING", "etd" => "DROPFULL");
    
  /* ** possible actions (emails): ** 
    Daily training reminder
    Day1 initial post-mtg
    Weekly Qs 
    Weekly Qs next-day reminder (if not yet submitted from KeySurvey)
    Post-training Qs
    Monthly Qs email
    Monthly Qs next-day reminder (if not yet submitted from KeySurvey)
    warnus tell us whose Qs have not come in in 48 hours [send from studyhandler@hts]
  */    


  /****** UTILITIES ******/
  function myExit($errorStatus, $echoBeforeExit) {
	  if(substr_count($errorStatus, "error")) {
      if($echoBeforeExit != "")
        alert($echoBeforeExit . ERROR_TELL_US);
	    $errorMessage = $_SERVER["PHP_SELF"] . "\n\nGET: " . print_r($_GET, true) . "\n\nPOST: " . print_r($_POST, true) . "\n\nGave this message to user on screen: " . $echoBeforeExit;
      if(!SIMULATE_EMAILS) {
        mail(ADMIN_EMAIL, "StudyHandler to Admin: ERROR REPORT", $errorMessage); // note, no use of myMail or "or" here because it's the final pathway of errors
	      if(DEBUG_EMAIL) mail(DEBUG_EMAIL, "StudyHandler to Debug email address: ERROR REPORT", $errorMessage);
      }
	    logIt("ERROR", $errorMessage);
    } else if($echoBeforeExit != "")
        echo "<br>$echoBeforeExit";
	  include "bottomstudyhandler.php";
    exit();
  }

  function logIt ($type, $logString) {
    static $dailyRunLog = ""; global $dailyRun;
    $handle = fopen(PATH_LOG, "a");
    if($dailyRunLog == "" && isset($dailyRun)) {
      $sessionStartEntry = implode(",", array("\r\n" . myToday(), date("g:i:s A"), "DAILYRUN START"));
      fwrite($handle, $sessionStartEntry);
      $dailyRunLog .= $sessionStartEntry;
    }
    if($type == "get dailyrun log") {
      $sessionEndEntry = implode(",", array("\r\n" . myToday(), date("g:i:s A"), "DAILYRUN END"));
      fwrite($handle, $sessionEndEntry);
      return $dailyRunLog . $sessionEndEntry;
    }
    $logEntry = implode(",", array("\r\n" . myToday(), date("g:i:s A"), $type, myTrim($logString)));
    fwrite($handle, $logEntry);
    fclose($handle);
    $dailyRunLog .= $logEntry;
    if(OPTION_ECHO_LOGGING)
      echo "<br><span class=\"logit\">Logged " . myToday() . " " . date("g:i:s A") . " - " . $type . " &#060;&#060; " . $logString . " &#062;&#062;</span>";
    if(rand(1,100) <= 100*BACKUP_LOG_PROBABILITY) {
      $currentLog = file_get_contents(PATH_LOG) or myExit("error", "Log reading error #8");
      file_put_contents("autobackup/log/log-" . date('Y-n-j-H\hi') . ".csv", $currentLog) or myExit("error", "Log writing error #9");
    }
  }

  function myMail($email, $subj, $msg) {
    if(SIMULATE_EMAILS) {
      if ($email == ADMIN_EMAIL) $email = "ADMIN [" . ADMIN_EMAIL . "]";
      else if ($email == DEBUG_EMAIL) $email = "DEBUG [" . DEBUG_EMAIL . "]";
      echo nl2br("\n<span style=\"color: green\"><b>Simulated email to $email:</b></span>\n" . "SUBJECT: $subj\nMESSAGE:\n$msg");
    }
    else
      mail($email, $subj, $msg) or myExit("error", "email to $email failed: $subj\n\n$msg");
  }
  
  /****** DISPLAY METHODS ******/
  function alert($alertString) {
    echo "<br><span class=\"alert\">$alertString</span>";
  }
  function userMessage($message) {
    echo "<br><em>$message</em>";
  }
  
  function echoPsTable() {
  	$traineesCount = 0;
    $tblMeetingTrack = "<table class=\"csv\" border=\"0\" cellpadding=\"0\" cellspacing=\"1\">
        <th colspan = \"5\"><h3>Current list: Meeting Track</h3></th><tr class=\"headrow\"><td>Name</td><td>Email</td><td>Status Start Date</td><td>Track</td><td>QSets Done</td><td>Day: Status</td></tr>";
    $tblOnlineOnly = "<table class=\"csv\" border=\"0\" cellpadding=\"0\" cellspacing=\"1\">
      <th colspan = \"5\"><h3>Current list: Online-only Track</h3></th><tr class=\"headrow\"><td>Name</td><td>Email</td><td>Status Start Date</td><td>Track</td><td>QSets Done</td><td>Day: Status</td></tr>";
    $dataLines = file(PATH_DATA) or myExit("error", "Data reading error #30");
    
		foreach(array_reverse($dataLines) as $lineNum => $dataLine) {
      $PLine = explode(",", $dataLine); $PLine[PLINE_STATUS] = myTrim($PLine[PLINE_STATUS]);
      $track = $PLine[PLINE_TRACK];
      // unset($PLine[PLINE_TRACK]); (used to not display track)
      // for display purposes...
	      if((dayNum($PLine[PLINE_STARTDATE]) >= 1) && (dayNum($PLine[PLINE_STARTDATE]) <= 28) && (substr_count($PLine[PLINE_STATUS], "active"))) {
	      	$PLine[PLINE_STATUS] = "<b>day " . str_pad((int) dayNum($PLine[PLINE_STARTDATE]), 2, "0", STR_PAD_LEFT) . "</b>: " . myTrim($PLine[PLINE_STATUS]);
	      	$traineesCount++;
	      }
	      else
	      	$PLine[PLINE_STATUS] = "day " . str_pad((int) dayNum($PLine[PLINE_STARTDATE]), 2, "0", STR_PAD_LEFT). ": " . myTrim($PLine[PLINE_STATUS]);
      $PLine[PLINE_EMAIL] = "<a name=\"{$PLine[PLINE_EMAIL]}\">{$PLine[PLINE_EMAIL]}</a>";

			$makeBold = "";
			if(isset($_GET["hi"])) {
				if(substr_count(myTrim($PLine[PLINE_EMAIL]), $_GET["hi"])) {
					$makeBold = " style=\"font-weight: bold;\"";
				}
			}
						
			$lineToAdd = "<tr$makeBold><td>" . implode("</td><td>", $PLine) . "</td></tr>";
      if(substr_count($track, "m"))
        $tblMeetingTrack .= $lineToAdd;
      else if(substr_count($track, "o"))
        $tblOnlineOnly .= $lineToAdd;
      else
        alert("ERROR: The following line of data didn't have a valid track 'm' or 'o' (the data file " . PATH_DATA . " could be missing a line break): " . implode(", ", $PLine));
    }
    echo "<p style = \"text-align: center;\">--- # of Ps currently in training: $traineesCount ---";
    echo $tblMeetingTrack . "</table>" . $tblOnlineOnly . "</table>";
  }

  function echoActionsTable($actions, $label, $targetDate) {
    if($actions == false) {
      echo "<center><h3 class=\"actionlist\">[Planned Actions $label, $targetDate - none]</h3></center>";
    } else {
      echo "<table class=\"csv\" border=\"0\" cellpadding=\"0\" cellspacing=\"1\">
          <th colspan = \"6\"><h3 class=\"actionlist\">Planned Actions for $label, $targetDate</h3></th><tr class=\"headrow actionlist\"><td>Action String</td><td>Name</td><td>Email</td><td>Status Start Date</td><td>Track</td><td>Day# of $label</td></tr>";
      foreach($actions as $action) {
        echo "<tr class=\"actionlist\"><td>";
        unset($action[ACTION_DATE]); // don't print target date (since it's in title)
        $action[] = 1 + dateDifference($action[ACTION_P_STARTDATE], $targetDate); // for display. This is like a custom dayNum().
        echo implode("</td><td>", $action) . "<br></td></tr>";
      }
      echo "</table>";
    }
  }
  
  /****** P MANAGEMENT METHODS ******/
  function addP($P_name, $P_email, $P_track) {
    global $parseList;
    $P_name = ucfirst(trim($P_name)); $P_email = strtolower(trim($P_email)); $P_track = strtolower(trim($P_track)); $P_ID = $_POST["P_ID"];
    $P_startDate = myToday();
    $currentData = file_get_contents(PATH_DATA);
    if($currentData === false)
      myExit("error", "Data reading error #14");
    else if($currentData !== "") {
      if (substr_count($currentData, $P_email) > 0) {
        alert("Couldn't add participant: Email address already in list.");
        return false;
      }
    }
    
    //Randomization here
    if(strlen($P_track) == 1) { 
			$theRandom = rand(1, 3); // Random int from 1 to 3, automatic seeding
	    if($theRandom == 1) {
	    	$P_track .= "d";
	    }
	    else {
	    	$P_track .= "n";
	    }
    }
		    
    $handle = fopen(PATH_DATA, "a") or myExit("error", "Data writing error #5");
    if($P_track == "mn")
      $P_status = "active";
    else if(($P_track == "md") || ($P_track == "od"))
      $P_status = "pre-del";
    else if($P_track == "on")
      $P_status = "pre-instructions";
    $P_status = strtoupper(substr($P_track, 1)) . "." . $P_status;
    fputcsv($handle,array($P_name, $P_email, $P_startDate, $P_track, "NONE", $P_status));
    fclose($handle);
    if($_POST["sendaddemail"]) {
      $addEmailLogAppend = " w/add email";
      $addEmailNote = "Sent ADD-" . strtoupper($P_track) . " email";
      $sendAddEmail = true;
    }
    else {
      $addEmailLogAppend = "";
      $addEmailNote = "Did NOT send Add Email";
      $sendAddEmail = false;
    }
    logIt("Added P$addEmailLogAppend", implode(",", array($P_name,$P_email,$P_startDate,$P_track,$P_ID)));
    if($sendAddEmail) {
      $msgFileLines = file(PATH_STUDYHANDLER_MESSAGES) or myExit("error", "Messages file reading error #39b");
      $dueAction = array(myToday(), "", $P_name, $P_email, $P_startDate, $P_track);
			mailPMessage($dueAction, "ADD-X", $msgFileLines);
      if($P_track == "mn") {
        $addEmailLogAppend .= " & DAY1 email";
        $addEmailNote .= " & DAY1 email";
        $parseTarget = $parseList["d1"];
        $dueAction = array(myToday(), "d1-send", $P_name, $P_email, $P_startDate, $P_track);
        mailPMessage($dueAction, $parseTarget, $msgFileLines);
      }
    }
    userMessage("Added $P_email ($P_name) to track <span style=\"font-size: 24px;\">$P_track</span>, start date $P_startDate, ID $P_ID. " . $addEmailNote . "<br>");
  }
  
  function dropPBefore($P_email) {
    $P_email = trim($P_email); 
    $currentData = file_get_contents(PATH_DATA) or myExit("error", "Data reading error #10q");
    if(substr_count($currentData, $P_email) > 1)
      myExit("error", "ERROR: multiple participants were found with that email address. (No removal done.)");
    else if (substr_count($currentData, $P_email) == 0) {
      alert("Couldn't remove participant: The email you entered is not in the list.");
      return false;
    }
    else {
      $dataLines = file(PATH_DATA) or myExit("error", "Data reading error #13q"); 
      foreach($dataLines as $lineNum => $dataLine) { 
        $PLine = explode(",", $dataLine); $PLine[PLINE_STATUS] = myTrim($PLine[PLINE_STATUS]);
        if(substr_count($PLine[PLINE_EMAIL], "@") == 0) alert("ERROR: there is a malformed or blank line in the data file (" . PATH_DATA . ") on line $lineNum. Continuing...");
        if(substr_count($PLine[PLINE_EMAIL], $P_email) == 1) {
          $dueAction = array(myToday(), "", $PLine[PLINE_NAME], $P_email, $PLine[PLINE_STARTDATE], $PLine[PLINE_TRACK]);
          $msgFileLines = file(PATH_STUDYHANDLER_MESSAGES) or myExit("error", "Messages file reading error #39q");
          mailPMessage($dueAction, "DROPBEFORE", $msgFileLines);
          userMessage("Sent DROPBEFORE message to {$PLine[PLINE_EMAIL]} ({$PLine[PLINE_NAME]})");
          logIt("DropBefore P", "$dataLine,day" . dayNum($PLine[PLINE_STARTDATE]));
          removeP($P_email);
          return true;
        }
      }
      myExit("error", "Unexpected Error #15q");
    }
  }

  function dropPEarly($P_email) {
    $P_email = trim($P_email); 
    $currentData = file_get_contents(PATH_DATA) or myExit("error", "Data reading error #10x");
    if(substr_count($currentData, $P_email) > 1)
      myExit("error", "ERROR: multiple participants were found with that email address. (No removal done.)");
    else if (substr_count($currentData, $P_email) == 0) {
      alert("Couldn't remove participant: The email you entered is not in the list.");
      return false;
    }
    else {
      $dataLines = file(PATH_DATA) or myExit("error", "Data reading error #13x"); 
      foreach($dataLines as $lineNum => $dataLine) { 
        $PLine = explode(",", $dataLine); $PLine[PLINE_STATUS] = myTrim($PLine[PLINE_STATUS]);
        if(substr_count($PLine[PLINE_EMAIL], "@") == 0) alert("ERROR: there is a malformed or blank line in the data file (" . PATH_DATA . ") on line $lineNum. Continuing...");
        if(substr_count($PLine[PLINE_EMAIL], $P_email) == 1) {
          $dueAction = array(myToday(), "", $PLine[PLINE_NAME], $P_email, $PLine[PLINE_STARTDATE], $PLine[PLINE_TRACK]);
          $msgFileLines = file(PATH_STUDYHANDLER_MESSAGES) or myExit("error", "Messages file reading error #39x");
          mailPMessage($dueAction, "DROPEARLY", $msgFileLines);
          userMessage("Sent DROPEARLY message (includes debriefing) to {$PLine[PLINE_EMAIL]} ({$PLine[PLINE_NAME]})");
          logIt("DropEarly P", "$dataLine,day" . dayNum($PLine[PLINE_STARTDATE]));
          removeP($P_email);
          return true;
        }
      }
      myExit("error", "Unexpected Error #15m");
    }
  }

  function dropPFull($P_email) {
    $P_email = trim($P_email); 
    $currentData = file_get_contents(PATH_DATA) or myExit("error", "Data reading error #10c");
    if(substr_count($currentData, $P_email) > 1)
      myExit("error", "ERROR: multiple participants were found with that email address. (No removal done.)");
    else if (substr_count($currentData, $P_email) == 0) {
      alert("Couldn't remove participant: The email you entered is not in the list.");
      return false;
    }
    else {
      $dataLines = file(PATH_DATA) or myExit("error", "Data reading error #13"); 
      foreach($dataLines as $lineNum => $dataLine) { 
        $PLine = explode(",", $dataLine); $PLine[PLINE_STATUS] = myTrim($PLine[PLINE_STATUS]);
        if(substr_count($PLine[PLINE_EMAIL], "@") == 0) alert("ERROR: there is a malformed or blank line in the data file (" . PATH_DATA . ") on line $lineNum. Continuing...");
        if(substr_count($PLine[PLINE_EMAIL], $P_email) == 1) {
          $dueAction = array(myToday(), "", $PLine[PLINE_NAME], $P_email, $PLine[PLINE_STARTDATE], $PLine[PLINE_TRACK]);
          $msgFileLines = file(PATH_STUDYHANDLER_MESSAGES) or myExit("error", "Messages file reading error #39c");
          mailPMessage($dueAction, "DROPFULL", $msgFileLines);
          $PLine[PLINE_STATUS] = substr($PLine[PLINE_STATUS], 0, 2) . "dropped-waitforetd";
          $oldStartDate = $PLine[PLINE_STARTDATE];
          $PLine[PLINE_STARTDATE] = myToday();
          $dataLines[$lineNum] = implode(",", $PLine) . "\r\n";
          file_put_contents(PATH_DATA, $dataLines) or myExit("error", "File writing error #16c");
          userMessage("Dropped (full) participant {$PLine[PLINE_EMAIL]} ({$PLine[PLINE_NAME]}), track {$PLine[PLINE_TRACK]}, updated status and Status Start Date (training start date was $oldStartDate), sent drop email, now awaiting etd completion, QSets done: {$PLine[PLINE_DONESTRING]})");
          logIt("DropFull P", "$dataLine,day" . dayNum($PLine[PLINE_STARTDATE]));
          return true;
        }
      }
      myExit("error", "Unexpected Error #15b");
    }
  }

  function removeP($P_email) {
    $P_email = trim($P_email); 
    $currentData = file_get_contents(PATH_DATA) or myExit("error", "Data reading error #10");
    if(substr_count($currentData, $P_email) > 1)
      myExit("error", "ERROR: multiple participants were found with that email address. (No removal done.)");
    else if (substr_count($currentData, $P_email) == 0) {
      alert("Couldn't remove participant: The email you entered is not in the list.");
      return false;
    }
    else {
      $dataLines = file(PATH_DATA) or myExit("error", "Data reading error #13"); 
      foreach($dataLines as $lineNum => $dataLine) { 
        $PLine = explode(",", $dataLine); $PLine[PLINE_STATUS] = myTrim($PLine[PLINE_STATUS]);
        if(substr_count($PLine[PLINE_EMAIL], "@") == 0) alert("ERROR: there is a malformed or blank line in the data file (" . PATH_DATA . ") on line $lineNum. Continuing...");
        if(substr_count($PLine[PLINE_EMAIL], $P_email) == 1) {
          unset($dataLines[$lineNum]);
          if(count($dataLines) == 0) {
            userMessage("There are now no more participants in your list. If there should be, then you could go via FTP right now to restore the backup from autobackup/data/data-viewing-" . date('Y-n-j-H\hi') . ".csv" . ", to rescue your data. Or Ask Phil what to do. ");
            file_put_contents(PATH_DATA, "");
          }
          else {
            file_put_contents(PATH_DATA, $dataLines) or myExit("error", "File writing error #16");
            userMessage("Removed participant {$PLine[PLINE_EMAIL]} ({$PLine[PLINE_NAME]}), track {$PLine[PLINE_TRACK]}, status start date {$PLine[PLINE_STARTDATE]}, QSets done: {$PLine[PLINE_DONESTRING]})");
          }
          logIt("Removed P", "$dataLine,day" . dayNum($PLine[PLINE_STARTDATE]));
          return true;
        }
      }
      myExit("error", "Unexpected Error #15");
    }
  }

  function setPStartDate($P_email, $newStartDate) {
    $currentData = file_get_contents(PATH_DATA) or myExit("error", "Data reading error #100");
    if(substr_count($currentData, $P_email) > 1)
      myExit("error", "ERROR: multiple participants were found with that email address. (No changes made.)");
    else if (substr_count($currentData, $P_email) == 0) {
      alert("Couldn't find participant: The email you entered is not in the list.");
      return false;
    }
    else {
      $dataLines = file(PATH_DATA) or myExit("error", "Data reading error #130"); 
      foreach($dataLines as $lineNum => $dataLine) { 
        $PLine = explode(",", $dataLine); $PLine[PLINE_STATUS] = myTrim($PLine[PLINE_STATUS]);
        if(substr_count($PLine[PLINE_EMAIL], $P_email) == 1) {
          if(substr_count($PLine[PLINE_EMAIL], "@") == 0) alert("ERROR: there is a malformed or blank line in the data file (" . PATH_DATA . ") on line $lineNum. Continuing...");
          $oldStartDate = $PLine[PLINE_STARTDATE];
          $PLine[PLINE_STARTDATE] = $newStartDate;
          $dataLines[$lineNum] = implode(",", $PLine) . "\r\n";
          file_put_contents(PATH_DATA, $dataLines) or myExit("error", "File writing error #160");
          logIt("Changed startdate from $oldStartDate to $newStartDate", $dataLine);
          userMessage("Changed start date of participant {$PLine[PLINE_EMAIL]} ({$PLine[PLINE_NAME]}) from $oldStartDate to $newStartDate");
          return true;
        }
      }
      myExit("error", "Unexpected Error #15c");
    }
  }
  
  /****** DATE MATH METHODS ******/
  /* daysBetween() Returns # of days of second date minus first date
    arg format: M/D/Y *///    today, targetDate
  function dateDifference($firstDate, $secondDate) {
    // doing these both with strtotime because strtotime sets HMS to 00:00:00, so it'll do the same to both
    $secondsDiff = strtotime($secondDate) - strtotime($firstDate);
    $daysDiff = floor($secondsDiff/(60*60*24));
    return $daysDiff;
  }

  function dayNum($startDate) {
    return 1 + dateDifference($startDate, myToday());
  }

  /****** ACTIONS METHODS ******/
  /* getDueActionStrings() returns an array of action strings for 1 P due on target date given a start date or returns false if none. Does not check status. */
  function getDueActionStrings($startDate, $targetDate) {
    global $actionTags;
    foreach($actionTags as $actionTag => $actionDayNum) {
      $command = "";
      if(dateDifference($startDate, $targetDate) != 0) { // (note1 - don't do anything on day1, since initial emails should be taken care of by addP, not by dailyrun)
        if(dateDifference($startDate, $targetDate) == $actionDayNum - 1) $command = "send"; // day of
        else if((dateDifference($startDate, $targetDate) == $actionDayNum)
						 || (dateDifference($startDate, $targetDate) == $actionDayNum + 1)) $command = "remind"; // the 2 days after send... these offsets also referenced in getDueActions()
        else if(dateDifference($startDate, $targetDate) == $actionDayNum + 2) $command = "warnus"; // 3 days after

        if($command != "") {
          $dueActionStrings[] = $actionTag . "-" . $command;
          if($command == "warnus") $dueActionStrings[] = $actionTag . "-remind"; // send reminder to P as well when warning us 
        }
      }
    }
    if(dateDifference($startDate, $targetDate) >= 1 && dateDifference($startDate, $targetDate) <= LENGTH_OF_TRAINING - 2) { // no daily email on last day of training, since we have "lastday" below
      $dueActionStrings[] = "daily-send";
    }
    else if(dateDifference($startDate, $targetDate) == LENGTH_OF_TRAINING - 1) {
      $dueActionStrings[] = "lastday-send";
    }
    if(isset($dueActionStrings))
      return $dueActionStrings;
    else
      return false;
  }

  /* getDueActions() reads the whole CSV and returns all actions to be done for all Ps on targetdate (taking everything into account, such as status, track, QSets Done already) or returns false if none
   * It calls getDueActionStrings(startDate, targetDate) to find out what is due for a given P */
  function getDueActions($targetDate) {
    global $actionTags;
    $dataLines = file(PATH_DATA) or myExit("error", "Data reading error #31");
    foreach($dataLines as $lineNum => $dataLine) {
      $PLine = explode(",", $dataLine); $PLine[PLINE_STATUS] = myTrim($PLine[PLINE_STATUS]);
      if(count($PLine) <= PLINE_STATUS)
        myExit("error", "ERROR: Invalid line found in StudyHandler data file (" . PATH_DATA . "). Exiting.");
      
			if($dueActionStringsForP = getDueActionStrings($PLine[PLINE_STARTDATE], $targetDate)) {
        if(substr_count($PLine[PLINE_STATUS], "active")) {
          foreach($dueActionStringsForP as $dueActionString) {
	          if(substr($dueActionString, 0, 1) != "p") {
							$actionTag = substr($dueActionString, 0, strpos($dueActionString, "-"));
	            if(!substr_count($PLine[PLINE_DONESTRING], $actionTag))
	              $dueActions[] = array($targetDate, $dueActionString, $PLine[PLINE_NAME], $PLine[PLINE_EMAIL], $PLine[PLINE_STARTDATE], $PLine[PLINE_TRACK]);
            }
          }
          if(dateDifference($PLine[PLINE_STARTDATE], $targetDate) == 3) {
          	$dueActions[] = array($targetDate, "xx-howgoingus", $PLine[PLINE_NAME], $PLine[PLINE_EMAIL], $PLine[PLINE_STARTDATE], $PLine[PLINE_TRACK]);
          }
        }
        else if(substr_count($PLine[PLINE_STATUS], "delay")) {
          foreach($dueActionStringsForP as $dueActionString) {
	          if(substr($dueActionString, 0, 1) == "p") {
							$actionTag = substr($dueActionString, 0, strpos($dueActionString, "-"));
	            if(!substr_count($PLine[PLINE_DONESTRING], $actionTag))
	              $dueActions[] = array($targetDate, $dueActionString, $PLine[PLINE_NAME], $PLine[PLINE_EMAIL], $PLine[PLINE_STARTDATE], $PLine[PLINE_TRACK]);
            }
          }
        }
        else if(substr_count($PLine[PLINE_STATUS], "pre-instructions")) {
          if(substr_count($PLine[PLINE_TRACK], "d"))
						$tmpActionTag = "dir";
					else
						$tmpActionTag = "ir";
					if(substr_count($PLine[PLINE_DONESTRING], $tmpActionTag)) myExit("error", "unexpected error #917");
          if(dateDifference($PLine[PLINE_STARTDATE], $targetDate) >= 1 && dateDifference($PLine[PLINE_STARTDATE], $targetDate) <= 3)
            $dueActions[] = array($targetDate, $tmpActionTag . "-remind", $PLine[PLINE_NAME], $PLine[PLINE_EMAIL], $PLine[PLINE_STARTDATE], $PLine[PLINE_TRACK]);
          if(dateDifference($PLine[PLINE_STARTDATE], $targetDate) == 4) {
            $dueActions[] = array($targetDate, $tmpActionTag . "-remind", $PLine[PLINE_NAME], $PLine[PLINE_EMAIL], $PLine[PLINE_STARTDATE], $PLine[PLINE_TRACK]);
            if(substr_count($PLine[PLINE_TRACK], "n"))
							$dueActions[] = array($targetDate, "ir-warnus", $PLine[PLINE_NAME], $PLine[PLINE_EMAIL], $PLine[PLINE_STARTDATE], $PLine[PLINE_TRACK]);
          }
        }
        else if(substr_count($PLine[PLINE_STATUS], "dropped-waitforetd")) {
          if(substr_count($PLine[PLINE_DONESTRING], "etd")) myExit("error", "unexpected error #917b");
          if(dateDifference($PLine[PLINE_STARTDATE], $targetDate) >= 1 && dateDifference($PLine[PLINE_STARTDATE], $targetDate) <= 3)
            $dueActions[] = array($targetDate, "etd-remind", $PLine[PLINE_NAME], $PLine[PLINE_EMAIL], $PLine[PLINE_STARTDATE], $PLine[PLINE_TRACK]);
          if(dateDifference($PLine[PLINE_STARTDATE], $targetDate) == 4) {
            $dueActions[] = array($targetDate, "etd-remind", $PLine[PLINE_NAME], $PLine[PLINE_EMAIL], $PLine[PLINE_STARTDATE], $PLine[PLINE_TRACK]);
						$dueActions[] = array($targetDate, "etd-warnus", $PLine[PLINE_NAME], $PLine[PLINE_EMAIL], $PLine[PLINE_STARTDATE], $PLine[PLINE_TRACK]);
          }
        }
      }
    }
    if(isset($dueActions)) return $dueActions;
    else return false;
  }
  
  /****** MISC METHODS ******/
  function clearBlankRows($data) {
    $numReplacements = -99;
    if(strpos($data,"\r\n") === 0) // cuts out first blank line, if any
      $data = substr($data, 4);
    while($numReplacements) {
      if($numReplacements != -99) userMessage("Found and removed $numReplacements blank lines from data file " . PATH_DATA . ", which aren't normally there (did you edit the file manually?). Shouldn't be a problem, but perhaps ask Phil.");
      $numReplacements = 0;
      // Okay so I'm a little paranoid here, because sometimes newlines show up as just \n and other times as \r\n
      $data = str_replace("\r\n\r\n", "\r\n", $data, $numReplacementsReceiver); $numReplacements += $numReplacementsReceiver;
      $data = str_replace("\n\n", "\n", $data, $numReplacementsReceiver); $numReplacements += $numReplacementsReceiver;
      $data = str_replace("\r\r", "\r", $data, $numReplacementsReceiver); $numReplacements += $numReplacementsReceiver;
      $data = str_replace("\n\r", "\n\r", $data, $numReplacementsReceiver); $numReplacements += $numReplacementsReceiver;
    }
    return $data;
  }
  
  function checkAddForm() {
    if(isset($_POST["P_name"])) $P_name = trim($_POST["P_name"]);
    if(isset($_POST["P_email_to_add"])) $P_email = trim($_POST["P_email_to_add"]);
    if(isset($_POST["P_ID"])) $P_ID = trim($_POST["P_ID"]);
    if(isset($_POST["P_track"])) $P_track = strtolower(trim($_POST["P_track"]));
    if($P_name == null || $P_email == null || $P_ID == null || $P_track == null) {
      alert("Couldn't add participant: You forgot to type in one of the fields.");
      return false;
    }
    // check for spaces other than at start or end, valid email:
    if(substr_count($P_email . $P_track, " ")
        || substr_count($P_name,"@") || substr_count($P_name,",") || substr_count($P_email,",") || !substr_count($P_email, "@") || !substr_count($P_email, ".")
        || ($P_track != "mn" && $P_track != "on" && $P_track != "od" && $P_track != "o")) {
      alert("Couldn't add participant: something's wrong with what you typed in.");
      return false;
    }
    return true;
  }

  /***** DO METHODS (main page functioning) *****/
  /* doDailyRun() is for cron job or viewing simulations of doing actions. It's called when $dailyRun is set (which is done by dailyrun.php which does include "index.php"). */
  function doDailyRun() {
    global $parseList, $actionTags;
    $currentData = file_get_contents(PATH_DATA);
    if(!$currentData) {
      userMessage("Either you have no participants in the system (or there's an error reading the data file), " . PATH_DATA . ". StudyHandler will not take any action or display anything further until you add a participant.");
      displayInputInterface();
      myExit("error", "Error #6c: or unreadable data file");
    }
    file_put_contents("autobackup/data/data-dailyrun-" . date('Y-n-j-H\hi') . ".csv", $currentData) or myExit("error", "Data writing error #35");;
    $currentLog = file_get_contents(PATH_LOG) or myExit("error", "Log reading error #32");
    file_put_contents(PATH_LOG_BAK_LASTRUN, $currentLog) or myExit("error", "Log writing error #33");
    $msgFileLines = file(PATH_STUDYHANDLER_MESSAGES) or myExit("error", "Messages file reading error #38");
    $dueActions = getDueActions(myToday());
    if($dueActions === false) myExit("ok", "No actions to do.");
    foreach($dueActions as $dueAction) {
      if($dueAction[ACTION_DATE] != myToday())
        myExit("error", "Date error #36, dueAction[ACTION_DATE] is " . $dueAction[ACTION_DATE]);
      $actionTag = substr($dueAction[ACTION_STRING], 0, strpos($dueAction[ACTION_STRING], "-"));
      if(substr_count($dueAction[ACTION_STRING], "warnus")) {
        if($actionTag == "ir") $actionTag = "instructions";
        $subj = "SH1.1: 'warnus': P {$dueAction[ACTION_P_EMAIL]} ({$dueAction[ACTION_P_NAME]}, day " . dayNum($dueAction[ACTION_P_STARTDATE]) . ") for '$actionTag'.";
        $msg = "SH1.1 line link: http://handheldtrainingstudy.com/studyhandler/?hi={$dueAction[ACTION_P_EMAIL]}#{$dueAction[ACTION_P_EMAIL]}\n\nIt's been 3 days since that QSet (or instructions) was first sent to them.";
        myMail(ADMIN_EMAIL, $subj, $msg); 
      }
      else if(substr_count($dueAction[ACTION_STRING], "howgoingus")) {
        $subj = "SH1.1: Send a \"how's it going\" checkup email to {$dueAction[ACTION_P_EMAIL]} ({$dueAction[ACTION_P_NAME]})";
        $msg = "SH1.1 line link: http://handheldtrainingstudy.com/studyhandler/?hi={$dueAction[ACTION_P_EMAIL]}#{$dueAction[ACTION_P_EMAIL]}";
        myMail(ADMIN_EMAIL, $subj, $msg); 
      }
      else {
        $parseTarget = $parseList[$actionTag];
        mailPMessage($dueAction, $parseTarget, $msgFileLines);
      }
      logIt($dueAction[ACTION_STRING], implode(",", array($dueAction[ACTION_P_NAME], $dueAction[ACTION_P_EMAIL], $dueAction[ACTION_P_STARTDATE], "day " . dayNum($dueAction[ACTION_P_STARTDATE]), $dueAction[ACTION_P_TRACK])));
    }
    myMail(ADMIN_EMAIL, "SH1.1 for-records: Daily run just completed. Log enclosed...", logIt("get dailyrun log", ""));
    myExit("ok", "<br>--Daily run complete--");
  }

  /* doAdminPage() is called when $dailyRun is not set */
  function doAdminPage() {
    global $parseList, $actionTags;
    $t = SIMULATE_DATE ? "<span class=\"alert\">SIMULATED</span>" : "";
    echo "<h2>" . PAGE_ID . "</h2><center><em>Today's $t date: " . myToday() . "</em><br><a href=\"viewlog.php\">View StudyHandler Log</a> ... <a href=\"viewlog-qs.php\">View QSetHandler Log</a> ... <a href=\"studyhandler-messages-editable.txt\">View Text of Emails Sent by StudyHandler</a> ... <a href=\"logout.php\">Log Out</a></center><p>";
    if(isset($_POST["drop_P"])) {
      if(!isset($_POST["dropaction"]))
        alert("You need to choose one of the specific Drop/Remove options");
      else {
        if($_POST["dropaction"] == "dropbefore")
          dropPBefore($_POST["P_email_to_drop"]);
        else if($_POST["dropaction"] == "dropearly")
          dropPEarly($_POST["P_email_to_drop"]);
        else if($_POST["dropaction"] == "dropfull")
          dropPFull($_POST["P_email_to_drop"]);
        else if($_POST["dropaction"] == "removeonly")
          removeP($_POST["P_email_to_drop"]);
      }
    }
    $currentData = file_get_contents(PATH_DATA);
    if(!$currentData) {
      if(!isset($_POST["add_P"])) {
        userMessage("Either you have no participants in the system, or there's an error reading the data file, " . PATH_DATA . ". You can add a participant below.");
        displayInputInterface();
        myExit("ok", "");
      }
    }
    else
      file_put_contents("autobackup/data/data-viewing-" . date('Y-n-j-H\hi') . ".csv", $currentData) or myExit("error", "Data writing error #7");
    if(isset($_POST["add_P"]) && checkAddForm())
      addP($_POST["P_name"], $_POST["P_email_to_add"], $_POST["P_track"]);
    else if(isset($_POST["change_P_startdate"])) {
      $dateAsArray = explode("/", $_POST["P_new_startdate"]);
      if(!checkdate($dateAsArray[0], $dateAsArray[1], $dateAsArray[2]))
        alert("Couldn't change participant: invalid date.");
      else
        setPStartDate($_POST["P_email_for_startdate_change"], $_POST["P_new_startdate"]);
      }
    displayInputInterface();
    echoPsTable();
    echoActionsTable(getDueActions(myToday()), "Today", myToday());
    $tomorrow = date("n/j/Y", strtotime("+1 day", strtotime(myToday())));
    echoActionsTable(getDueActions($tomorrow), "Tomorrow", $tomorrow);
    $yesterday = date("n/j/Y", strtotime("-1 day", strtotime(myToday())));
    echoActionsTable(getDueActions($yesterday), "Yesterday", $yesterday);
    myExit("ok", "");
  }
  
  function displayInputInterface() {
    ?>
    	<div class="leftdiv"><center>
        <div class="inputArea">
      	  <h3>Add participant</h3>
      		<form method="POST" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
      			Preferred Name: <input class="spaced" type="text" name="P_name" size="24"/>
      			<br>Email: <input class="spaced" type="text" name="P_email_to_add" value="" size="34"/>
      			<br>Login/ID (e.g. xy002): <input class="spaced" type="text" name="P_ID" value="" size="5"/>
      			<br>Track (mn, o): <input class="spaced" type="text" name="P_track" value="" size="2"/>
      			<br><input type="radio" name="sendaddemail" value="1" checked /><label style="vertical-align: bottom; position: relative; top: 0px;" />Add and send immediate email</label>
      			<br><input type="radio" name="sendaddemail" value="0" /><label style="vertical-align: bottom; position: relative; top: 0px;" />Just add to StudyHandler (no emailing)</label>
      			<br><input class="spaced" type="submit" name="add_P" value="Add Participant">
      		</form>
    	  </div>
				<h3>Useful info</h3>      
				
				<p>--- Reference: day numbers of QSet emails ---
				<br>w1 -> day 8, w2 -> 15, w3 -> 22, w4 -> 29, f1 -> 58, f2 -> 88

     	<?php 
      		$tomorrow = date("n/j/Y", strtotime("+1 day", strtotime(myToday())));
      		$yesterday = date("n/j/Y", strtotime("-1 day", strtotime(myToday())));
					echo "<p>--- # of emails sending @6pm (limit 750): "
						. count(getDueActions($yesterday)) . " yesterday, "
						. "" . count(getDueActions(myToday())) . " today, "
						. count(getDueActions($tomorrow)) . " tomorrow. ---<br>";
						
				?>
				

			</div></center>
      <div class="rightdiv"><center>
        <div class="inputArea">
      	  <h3>Drop/Remove participant</h3>
          <form style="text-align: left" method="POST" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
      			Email: <input class="spaced" type="text" name="P_email_to_drop" value="" size="32"/>
      			<br><input class="spaced" type="radio" name="dropaction" value="dropbefore" /><label style="vertical-align: bottom; position: relative; top: -6px;" /><i>Drop before P started training</i> (Notify P of drop, "It seems you have chosen not to participate," and remove from StudyHandler, no debriefing or exit questionnaire)</label>
      			<br><input class="spaced" type="radio" name="dropaction" value="dropearly" /><label style="vertical-align: bottom; position: relative; top: -6px;" /><i>Drop in first couple days of study or drop nonresponsive P</i> (Debrief and remove from StudyHandler, no exit questionnaire)</label>
      			<br><input class="spaced" type="radio" name="dropaction" value="dropfull" /><label style="vertical-align: bottom; position: relative; top: -6px;" /><i>Drop during study (full drop)</i> (Send exit questionnaire, debrief/remove from StudyHandler when P completes exit qst)</label>
      			<br><input class="spaced" type="radio" name="dropaction" value="removeonly" /><label style="vertical-align: bottom; position: relative; top: -9px;" /><i>Remove only, e.g. if you made a typo when adding, or if status is "removeme"</i> (Remove from StudyHandler, no email to P)</label>
      			<br><center><input class="spaced" type="submit" name="drop_P" value="Drop/Remove Participant"></center>
      		</form>
      	</div>
        <div class="inputArea">
      	  <h3>Change participant start date</h3>
      		<form method="POST" action="<?php echo $_SERVER["PHP_SELF"]; ?>">
      			Email: <input class="spaced" type="text" name="P_email_for_startdate_change" value="" size="41"/>
      			<br>New start date (M/D/YYYY): <input class="spaced" type="text" name="P_new_startdate" value="" size="13"/>
      			<br><input class="spaced" type="submit" name="change_P_startdate" value="Change Start Date">
      		</form>
      	 </div>

    	</div></center>
  	
  <?php
  }

  /****** VOID MAIN() ******/
  $lockHandle = fopen(PATH_LOCK_STUDYHANDLER, "w+") or myExit("error", "Error [locking problem] #1! Couldn't do anything.");
  flock($lockHandle,LOCK_EX) or myExit("error", "Error [locking problem] #2! Couldn't do anything."); // btw, I never close this lock because PHP automatically closes when the thread ends
  if(filesize(PATH_LOG) > 900000) myMail(ADMIN_EMAIL, "StudyHandler to Admin, WARNING", "File size of your log " . PATH_LOG . " is over 900KB... it is: " . (filesize(PATH_LOG) / 1024) . "KB. You should make it smaller.");
	if(isset($dailyRun))
    doDailyRun();
  else
    doAdminPage();
?>
