<?php
/**
 * $Date$
 * $Revision$
 * $HeadURL$
 * @package EDK
 */
/*
 * @package EDK
 */
class pHome extends pageAssembly
{
	function __construct()
	{
		parent::__construct();
		$this->queue('start');
		$this->queue('summaryTable');
		$this->queue('campaigns');
		$this->queue('contracts');
		$this->queue('killList');
		$this->view = preg_replace('/[^a-zA-Z0-9_-]/','',$_GET['view']);
		$this->viewList = array();

		$this->menuOptions = array();
	}

	function start()
	{
		$this->page = new Page();
		$this->page->addHeader("<link rel='canonical' href='".KB_HOST."/' />");
		if(isset($_GET['scl_id']) || isset($_GET['y'])) $this->page->addHeader('<meta name="robots" content="index, nofollow" />');
		$this->menuOptions = array();

		$this->scl_id = intval($_GET['scl_id']);

		$this->killcount = config::get('killcount');
		$this->hourlimit = config::get('limit_hours');
		if(!$this->hourlimit) $this->hourlimit = 1;
		$this->klreturnmax = 3;
		$this->showcombined = config::get('show_comb_home')
			&& (count(config::get('cfg_allianceid')) || count(config::get('cfg_corpid')) || count(config::get('cfg_pilotid')));

		$week = $month = $year = 0;
		if(isset($_GET['w'])) $week = intval($_GET['w']);
		if(isset($_GET['m'])) $month = intval($_GET['m']);
		if(isset($_GET['y'])) $year = intval($_GET['y']);
		$this->setTime($week, $year, $month);

		if($this->view == 'kills') $this->page->setTitle('Kills - '.$this->getCurrentPeriod());
		elseif($this->view == 'losses') $this->page->setTitle('Losses - '.$this->getCurrentPeriod());
		else $this->page->setTitle($this->getCurrentPeriod());
	}
	/**
	 *  Check if summary tables are enabled and if so return a table for this week.
	 *
	 * @return string HTML string for a summary table.
	 */
	function summaryTable()
	{
	// Display the summary table.
		if (config::get('summarytable'))
		{
			if (config::get('public_summarytable'))
			{
				//$kslist = new KillList();
				$summarytable = new KillSummaryTablePublic();
				$this->loadTime($summarytable);
				involved::load($summarytable,'kill');
			//$summarytable = new KillSummaryTablePublic($kslist);
			}
			else
			{
				$summarytable = new KillSummaryTable();
				$this->loadTime($summarytable);
				involved::load($summarytable, 'kill');
			}
			return $summarytable->generate();
		}
	}

	/**
	 * Returns HTML string for campaigns, if any.
	 * @return string HTML string for campaigns, if any
	 */
	function campaigns()
	{
	// Display campaigns, if any.
		if (Killboard::hasCampaigns(true) &&
			$this->isCurrentPeriod())
		{
			$html .= "<div class=\"kb-campaigns-header\">Active campaigns</div>";
			$list = new ContractList();
			$list->setActive("yes");
			$list->setCampaigns(true);
			$table = new ContractListTable($list);
			$html .= $table->generate();
			return $html;
		}
	}

	/**
	 * Returns HTML string for contracts, if any.
	 * @return string HTML string for contracts, if any
	 */
	function contracts()
	{
	// Display contracts, if any.
		if (Killboard::hasContracts(true) &&
			$this->isCurrentPeriod())
		{
			$html .= "<div class=\"kb-campaigns-header\">Active contracts</div>";
			$list = new ContractList();
			$list->setActive("yes");
			$list->setCampaigns(false);
			$table = new ContractListTable($list);
			$html .= $table->generate();
			return $html;
		}
	}

	/**
	 * Return the main killlists
	 * @global Smarty $smarty
	 * @return string HTML string for killlist tables
	 */
	function killList()
	{
		if(isset($this->viewList[$this->view])) return call_user_func_array($this->viewList[$this->view], array(&$this));

		global $smarty;

		$klist = new KillList();
		$klist->setOrdered(true);
		// We'll be needing comment counts so set the killlist to retrieve them
		if (config::get('comments_count')) $klist->setCountComments(true);
		// We'll be needing involved counts so set the killlist to retrieve them
		if (config::get('killlist_involved')) $klist->setCountInvolved(true);

		// Select between kills, losses or both.
		if($this->view == 'combined' || ($this->view == '' && $this->showcombined)) involved::load($klist,'combined');
		elseif($this->view == 'losses') involved::load($klist,'loss');
		else involved::load($klist,'kill');

		if ($this->scl_id)
			$klist->addVictimShipClass($this->scl_id);
		else
			$klist->setPodsNoobShips(config::get('podnoobs'));

		// If no week is set then show the most recent kills. Otherwise
		// show all kills for the week using the page splitter.
		if(empty($_GET['w']) && empty($_GET['y']) && config::get("cfg_fillhome"))
		{
			$klist->setLimit($this->killcount);
			$table = new KillListTable($klist);
			if($this->showcombined) $table->setCombined(true);
			$table->setLimit($this->killcount);
			$html .= $table->generate();
		}
		else
		{
			$this->loadTime($klist);
			//$klist->setWeek($this->week);
			//$klist->setYear($this->year);
			$klist->setPageSplit($this->killcount);
			$pagesplitter = new PageSplitter($klist->getCount(), $this->killcount);
			$table = new KillListTable($klist);
			if($this->showcombined) $table->setCombined(true);
			$pagesplit = $pagesplitter->generate();
			$html .= $pagesplit.$table->generate().$pagesplit;
		}
		return $html;
	}
	/**
	 * Set up the menu.
	 *
	 *  Prepare all the base menu options.
	 */
	function menuSetup()
	{
		// Display the menu for previous and next weeks.
		$this->addMenuItem("caption","Navigation");

		if($this->view == 'kills') $suffix = '&amp;view=kills';
		elseif($this->view == 'losses') $suffix .= '&amp;view=losses';
		if($this->scl_id) $suffixscl = '&amp;scl_id='.$this->scl_id;

		$this->addMenuItem("link","Previous ".$this->getPeriodName(),
			"?a=home&amp;" . $this->getPreviousPeriodLink() . $suffix.$suffixscl);
		if(!$this->isCurrentPeriod())
		{
			$this->addMenuItem("link","Next ".$this->getPeriodName(),
				"?a=home&amp;" . $this->getNextPeriodLink() . $suffix.$suffixscl);
		}
		$this->addMenuItem("link", "Kills",
			"?a=home&amp;" . $this->getCurrentPeriodLink() . '&amp;view=kills'.$suffixscl);
		$this->addMenuItem("link", "Losses",
			"?a=home&amp;" . $this->getCurrentPeriodLink() . '&amp;view=losses'.$suffixscl);
		if(config::get('show_comb_home')) $this->addMenuItem("link",
				$weektext."All Kills",
				"?a=home&amp;" . $this->getCurrentPeriodLink() . $suffixscl);
		return "";
	}
	/**
	 * Build the menu.
	 *
	 *  Add all preset options to the menu.
	 *
	 * @return string
	 */
	function menu()
	{
		$menubox = new box("Menu");
		$menubox->setIcon("menu-item.gif");
		foreach($this->menuOptions as $options)
		{
			if(isset($options[2]))
				$menubox->addOption($options[0],$options[1], $options[2]);
			else
				$menubox->addOption($options[0],$options[1]);
		}
		return $menubox->generate();
	}

	/**
	 *
	 * @return string HTML string for a clock
	 */
	function clock()
	{
	// Show the Eve time.
		if(config::get('show_clock'))
		{
			$clock = new Clock();
			return $clock->generate();
		}
	}

	/**
	 *
	 * @return string HTML string for toplists
	 */
	function topLists()
	{
	// Display the top pilot lists.
		if($this->view != 'losses')
		{
			$tklist = new TopList_Kills();
			$this->loadTime($tklist);
			involved::load($tklist,'kill');

			$tklist->generate();
			$tkbox = new AwardBox($tklist, "Top killers", "kills in " . $this->getCurrentPeriod(), "kills", "eagle");
			$html .= $tkbox->generate();
		}
		if($this->view == 'losses')
		{
			$tllist = new TopList_Losses();
			$this->loadTime($tllist);
			involved::load($tllist,'loss');

			$tllist->generate();
			$tlbox = new AwardBox($tllist, "Top losers", "losses in " . $this->getCurrentPeriod(), "losses", "moon");
			$html .= $tlbox->generate();
		}
		if ($this->view != 'losses')
		{
			$tklist = new TopList_Score();
			$this->loadTime($tklist);
			//$tklist->setWeek($this->week);
			//$tklist->setYear($this->year);
			involved::load($tklist,'kill');

			$tklist->generate();
			$tkbox = new AwardBox($tklist, "Top scorers", "points in " . $this->getCurrentPeriod(), "points", "redcross");
			$html .= $tkbox->generate();
		}
		return $html;
	}

	function context()
	{
		parent::__construct();
		$this->queue('menuSetup');
		$this->queue('menu');
		$this->queue('clock');
		$this->queue('topLists');
	}

	/**
	 * Return the set week.
	 * @return integer
	 */
	function getWeek()
	{
		return $this->week;
	}

	/**
	 * Return the set month.
	 * @return integer
	 */
	function getMonth()
	{
		return $this->month;
	}

	/**
	 * Return the set year.
	 * @return integer
	 */
	function getYear()
	{
		return $this->year;
	}

	/**
	 *
	 * @param integer $week
	 * @param integer $year
	 */
	function setWeek($week, $year)
	{
		$week = intval($week);
		$year = intval($year);
		// If a valid week and year are given then show that week.
		if(($year) < 2000 || ($week) < 1 || ($week) > getWeeks($year))
		{
			$week = kbdate('W');
			$year = kbdate('o');
		}

		$this->week = $week;
		if($this->week < 10) $this->week = '0'.$this->week;
		$this->year = $year;

		if ($this->week == 1)
		{
			$this->pyear = $this->year - 1;
			$this->pweek = getWeeks($this->pyear);
		}
		else
		{
			$this->pyear = $this->year;
			$this->pweek = $this->week - 1;
		}
		if ($this->week == getWeeks($this->year))
		{
			$this->nweek = 1;
			$this->nyear = $this->year + 1;
		}
		else
		{
			$this->nweek = $this->week + 1;
			$this->nyear = $this->year;
		}
		$this->periodName = 'Week';
		$this->period = 'Week '.$this->week.', '.$this->year;
		$this->currentTime =
			($this->week == kbdate('W') && $this->year == kbdate('o'));
		$this->previousPeriodLink = 'w='.$this->pweek.'&amp;y='.$this->pyear;
		$this->nextPeriodLink = 'w='.$this->nweek.'&amp;y='.$this->nyear;
		$this->currentPeriodLink = 'w='.$this->week.'&amp;y='.$this->year;
	}

	/**
	 *
	 * @param integer $month
	 * @param integer $year
	 */
	function setMonth($month, $year)
	{
		$month = (int)$month;
		$year = (int)$year;
		if($month < 1 || $month > 12 || $year < 2000)
		{
			$month = kbdate('m');
			$year = getYear();
		}
		$this->month = $month;
		if($this->month < 10) $this->month = '0'.$this->month;
		$this->year = $year;
		if($month == 1)
		{
			$this->pyear = $year - 1;
			$this->pmonth = 12;
		}
		else
		{
			$this->pyear = $year;
			$this->pmonth = $month - 1;
		}
		if ($this->month == 12)
		{
			$this->nmonth = 1;
			$this->nyear = $this->year + 1;
		}
		else
		{
			$this->nmonth = $this->month + 1;
			$this->nyear = $this->year;
		}
		$this->periodName = 'Month';
		$this->period = date('F', mktime(0,0,0,$this->month, 1,$this->year)).', '.$this->year;
		$this->currentTime =
			($this->month == kbdate('m') && $this->year == kbdate('Y'));
		$this->previousPeriodLink = 'm='.$this->pmonth.'&amp;y='.$this->pyear;
		$this->nextPeriodLink = 'm='.$this->nmonth.'&amp;y='.$this->nyear;
		$this->currentPeriodLink = 'm='.$this->month.'&amp;y='.$this->year;
	}
	/**
	 * Returns true if the board is showing the current time period.
	 * @return boolean true if the board is showing the current time period, false
	 *  otherwise.
	 */
	function isCurrentPeriod()
	{
		return $this->currentTime;
	}

	/**
	 *  Return the text name of the current time period type.
	 *
	 * @return string
	 */
	function getPeriodName()
	{
		return $this->periodName;
	}
	/**
	 *  Return the text name of the current time period.
	 */
	function getCurrentPeriod()
	{
		return $this->period;
	}

	/**
	 *  Return a string to add to a url to generate the current time period.
	 *
	 * @return string
	 */
	function getCurrentPeriodLink()
	{
		return $this->currentPeriodLink;
	}

	/**
	 *  Return a string to add to a url to generate the next time period.
	 *
	 * @return string
	 */
	function getNextPeriodLink()
	{
		return $this->nextPeriodLink;
	}

	/**
	 *  Return a string to add to a url to generate the previous time period.
	 *
	 * @return string
	 */
	function getPreviousPeriodLink()
	{
		return $this->previousPeriodLink;
	}

	function loadTime(&$object)
	{
		if($this->week)
		{
			$object->setWeek($this->week);
			$object->setYear($this->year);
		}
		elseif($this->month)
		{
			$start = makeStartDate($this->week, $this->year, $this->month);
			$end = makeEndDate($this->week, $this->year, $this->month);
			$object->setStartDate(gmdate('Y-m-d H:i',$start));
			$object->setEndDate(gmdate('Y-m-d H:i',$end));
			//die('start='.gmdate('Y-m-d H:i',$start).'end='.gmdate('Y-m-d H:i',$end));

		}
	}
	/**
	 *  Set the current time to use for this page.
	 *
	 * @param integer $week
	 * @param integer $year
	 * @param integer $month
	 * @param integer $start
	 * @param integer $end
	 */
	function setTime($week = 0, $year = 0, $month = 0, $start = 0, $end = 0)
	{
		// Set week.
		if($week && $year)
		{
			$this->setWeek($week, $year);
			$this->month = 0;
		}
		elseif($month && $year)
		{
			$this->setMonth($month, $year);
			$this->week = 0;
		}
		else
		{
 			if(config::get('show_monthly'))
			{
				$this->setMonth(kbdate('m'), kbdate('Y'));
			}
			else
			{
				$this->setWeek(kbdate('W'), kbdate('o'));
			}
		}
	}

	/**
	 * Add an item to the menu in standard box format.
	 *
	 *  Only links need all 3 attributes
	 * @param string $type Types can be caption, img, link, points.
	 * @param string $name The name to display.
	 * @param string $url Only needed for URLs.
	 */
	function addMenuItem($type, $name, $url = '')
	{
		$this->menuOptions[] = array($type, $name, $url);
	}

	/**
	 * Add a type of view to the options.
	 *
	 * @param string $view The name of the view to recognise.
	 * @param mixed $callback The method to call when this view is used.
	 */
	function addView($view, $callback)
	{
		$this->viewList[$view] = $callback;
	}
}

$pageAssembly = new pHome();
event::call("home_assembling", $pageAssembly);
$html = $pageAssembly->assemble();
$pageAssembly->page->setContent($html);

$pageAssembly->context(); //This resets the queue and queues context items.
event::call("home_context_assembling", $pageAssembly);
$contextHTML = $pageAssembly->assemble();
$pageAssembly->page->addContext($contextHTML);


$pageAssembly->page->generate();
