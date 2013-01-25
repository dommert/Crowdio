<?php

/*
* @Package:	Crowdio
*
*/

class CrowdioComment extends Crowdio {
	
	function __construct()
	{
		$GLOBALS['crowdio_comment_blank_fields'] = array();
	}

	function display_comment_form()
	{	// Show the form to submit a new idea, or a reply to an existing idea.
		global $current_user, $wpdb;
		get_currentuserinfo();
		$user_url = $current_user->user_url;
		$display_name = $current_user->display_name;
		$user_email = $current_user->user_email;
		$user_id = $current_user->ID;
		$commentParent = NULL;

		$action_url = $_SERVER["REQUEST_URI"];
		$user_comment = isset($_POST['crowdio_comment_content']) ? $_POST['crowdio_comment_content'] : NULL;
		$rfi_id = $GLOBALS['post']->ID;

		// Check for an existing top level comment (idea) submitted by this user on this RFI.
		$existingComment = $wpdb->get_row("SELECT * FROM " . CROWDIO_COMMENT_TABLE_NAME . " WHERE user_id = $user_id AND rfi_id = '$rfi_id'");
		
		if (is_user_logged_in())
		{	// Only show the form to logged in users.

			if (!empty($_GET['replyto']))
			{	// User clicked reply link, set the parent and check to make sure there is an existing comment that matches:
				$commentParent = isset($_GET['replyto']) ? $_GET['replyto'] : NULL;
				$commentParentExistsCheck = $wpdb->get_row("SELECT * FROM " . CROWDIO_COMMENT_TABLE_NAME . " WHERE ID = '" . $_GET['replyto'] . "'");
			} else
			{	// User is not currently replying, so make sure parent is not set.
				$commentParent = NULL;
			}

			if (empty($existingComment) || !empty($_GET['replyto']))
			{ 	// If there is already a matching comment (idea) from this user,
				// OR if the use clicked on reply.

				if (!empty($_GET['replyto']) && (empty($commentParentExistsCheck)))
				{	// Trying to reply to nonexistent comment.
					// Wipe 'replyto' and ignore it.
					$_GET['replyto'] = NULL;
					unset($commentParent);
				}
				// Grab error text for display below.
				$errors = isset($GLOBALS['crowdio_comment_submit_error']) ? $GLOBALS['crowdio_comment_submit_error'] : NULL;
				// Show the error(s).
				print <<<END
					<div class="crowdio_form_error">
						<span class="error">$errors</span>
					</div>
END;
				
				// Set up the message about which URL will be used on ideas.
				$addCommentInstructionWebsite = !empty($user_url) ? 
					"Your website address on your profile is <i>$user_url</i> <BR>" : 
					'Your website address is <b>blank</b> on your profile. <BR>';
				// Append to this URL message how to change the URL on their profile.
				$addCommentInstructionWebsite .= ' You can <a href="/wp-admin/profile.php">edit your profile</a> at any time.';

				$replyOrIdea = empty($_GET['replyto']) ? "reply" : "idea";
				// Set up prompt text depending on if this is a reply or a top level idea.
				$replyOrIdeaPrompt = ($replyOrIdea = "idea") ?
					'Write your idea here: <BR>' :
					'Write your reply here: <BR>';

				// Add a special class to the comment form field if it was blank.
				$commentContentClass = in_array('crowdio_comment_content', $GLOBALS['crowdio_comment_blank_fields']) ? 
					"submittedBlank" :
					"";

				// Message for submitting top level idea (not used for replies):
				$idea_message = "";
				if ($replyOrIdea = "idea") {
					$ideaMessage = <<<END
									<div id="crowdioAddCommentInstructionOne">
										Add your best idea (one per person):
									</div>
							    	<div id="crowdioAddCommentInstructionUser">
							    		Your idea will be left as <i>$display_name</i>.
							    	</div>
END;
				}
				// Finally we can show the form:
				print <<<END
					<a name="replyform">&nbsp</a>
					<div class="crowdio_form">
					    <form method="post" action="$action_url">
					    	<input type="hidden" name="crowdio_rfi_id" value="$rfi_id" />
					    	<input type="hidden" name="crowdio_comment_name" value="$display_name" />
					    	<input type="hidden" name="crowdio_comment_email" value="$user_email" />
					    	<input type="hidden" name="crowdio_comment_url" value="$user_url" />
					    	<input type="hidden" name="crowdio_comment_user_id" value="$user_id" />
					    	<input type="hidden" name="crowdio_comment_parent_id" value="$commentParent" />
					    	<input type="hidden" name="crowdio_comment_submit" value="verify" />

						    <div id="crowdioAddIdeaInstructions">
						    $idea_message
						    	<div id="crowdioAddCommentInstructionWebsite">$addCommentInstructionWebsite</div>
						    </div>
						    
						    <fieldset>
						    	<label for="crowdio_comment_content"><BR>$replyOrIdeaPrompt</label>
						    	<textarea class="$commentContentClass" rows="4" cols="40" id="crowdio_comment_content" name="crowdio_comment_content">$user_comment</textarea>
						    </fieldset>
				
					        <div class="crowdio_row"> <input type="submit" value="Save" id="submit" name="submit"  /> </div>
					    </form>
					</div>
END;
			} else
			{	// User has already submitted an idea, so do not display main idea form.
				print <<<END
					<div class="crowdioFormNotice">
						<span class="noticeText">You have already submitted an idea here. You can still reply to other ideas though!</span>
					</div>
END;
			}
		} else
		{	// User is not logged in, so do not display any form.
			$current_address_including_action = $_SERVER["REQUEST_URI"];
			print <<<END
				<div class="crowdioFormNotice">
					<span class="noticeText">Please <a href="/wp-login.php?redirect_to=$current_address_including_action">Log In</a> or <a href="/wp-login.php?action=register">Register</a> to add your idea.</span>
				</div>
END;
		}
	}

	function add_comment()
	{	// Adds comment to the database, 
		// or actually just sets up data and passes to $crowdio_db->insert_comment().
		global $wpdb, $current_user;
		$sid = session_id();
		get_currentuserinfo();


		if (is_user_logged_in())
		{	parent::explain("Getting ready to insert comment since the user is logged in.", 3);
			// We test this twice to filter out any rogue submits.
			// We also test to make sure the referrer was this site...
			$name = $_POST['crowdio_comment_name'];
			$email = $_POST['crowdio_comment_email'];
			$comment_text = $_POST['crowdio_comment_content'];
			$user_ip = $_SERVER['REMOTE_ADDR'];
			$user_id = $_POST['crowdio_comment_user_id'];
			$user_url = $_SERVER['crowdio_comment_user_url'];
			$session_id = session_id();
			$rfi_id = $_POST['crowdio_rfi_id'];
			$crowdio_db = new CrowdioDatabase();
			$parent_id = $_POST['crowdio_comment_parent_id'];
			
			$result = $crowdio_db->insert_comment($name, $email, $comment_text, $user_ip, $user_id, $user_url, $session_id, $rfi_id, $parent_id);
			if ($result)
			{	
				parent::explain("Comment appears to be added.", 3);
				unset($_POST);
			} else {
				parent::explain("PROBLEM: Our comment was not added.", 1);
			}
		} else
		{	
			parent::explain("Can't save comment, user not logged in.", 1);
		}
	}

	function display_comment($comment_row, $levelclass)
	{	// Display one comment, this is called from display_comments() multiple times, 
		// $comment_row is an object containing all of the data for just one comment, 
		// and $levelclass is a string setting the class name indicating how deep this 
		// comment is.
		global $wpdb;
		$commentUserData = get_userdata($comment_row->user_id);
		
		if ($commentUserData)
		{
			$current_visitor_user_id = get_current_user_id();
			$user_id = $comment_row->user_id;
			$created = date(get_option('date_format')." ".get_option('time_format'), strtotime($comment_row->created_timestamp));
			$name = $commentUserData->display_name;
			$url = $commentUserData->user_url;
			$comment = stripcslashes($comment_row->comment_text);
			$comment_id = $comment_row->ID;
			$rfi_id = $GLOBALS['post']->ID;

			// Url of current page (without any GET vars):
			$current_page_url = get_permalink( $rfi_id );
			
			// Check for existing vote on this comment by this user:
			$existing_upvote = $wpdb->get_row("SELECT * FROM " . CROWDIO_VOTE_TABLE_NAME . " WHERE comment_id = '$comment_id' AND user_id = '$current_visitor_user_id' AND positive = 1");
			$existing_downvote = $wpdb->get_row("SELECT * FROM " . CROWDIO_VOTE_TABLE_NAME . " WHERE comment_id = '$comment_id' AND user_id = '$current_visitor_user_id' AND negative = '1'");

			// Set special classes and icon for if user has already voted:
			$up_vote_icon = "&#8743;"; $down_vote_icon = "&#8744;";
			if ($existing_upvote) {
				$has_voted_class = "currentUserHasVotedUp";
				$up_vote_icon = "&#10003";
			} elseif ($existing_downvote) {
				$has_voted_class = "currentUserHasVotedDown";
				$down_vote_icon = "&#10003";
			} else {
				$has_voted_class = "currentUserHasNotVoted";
			}
			
			// Reply link URL:
			$reply_link_url = $current_page_url . "?replyto=$comment_id#replyform";

			if (!is_user_logged_in()) {
				// Prepend action vote/reply action link urls with login redirect if visitor not logged in.
				$crowdio_vote_up_url =  $crowdio_vote_down_url = HOME_URL . "/wp-login.php?redirect_to=$current_page_url";
				$reply_link_url = HOME_URL . "/wp-login.php?redirect_to=$current_page_url";

			} else {
				// Set vote link urls depending on if user has voted
				$crowdio_vote_up_url .= $existing_upvote ? 
					"$current_page_url?crowdio_unvote=up&comment_id=$comment_id" : // Unvote up
					"$current_page_url?crowdio_vote=up&comment_id=$comment_id"; // Regular vote up
				$crowdio_vote_down_url .= $existing_downvote ? 
					"$current_page_url?crowdio_unvote=down&comment_id=$comment_id" : // Unvote down
					"$current_page_url?crowdio_vote=down&comment_id=$comment_id&rfi_id=$rfi_id"; // Regular vote up
			}
		    
			// Comment votes
			$comment_upvotes_count = $wpdb->get_var("SELECT COUNT(*) FROM " . CROWDIO_VOTE_TABLE_NAME . " WHERE comment_id = '$comment_id' AND positive = '1'");
			$comment_downvotes_count = $wpdb->get_var("SELECT COUNT(*) FROM " . CROWDIO_VOTE_TABLE_NAME . " WHERE comment_id = '$comment_id' AND negative = '1'");

			// Tally basic score of vote (upvotes - downvotes).
			$comment_vote_score = $comment_row->upvotes - $comment_row->downvotes;
			
			// Set up dofollow/nofollow for comment author link.
			$follow = $comment_vote_score > 10 ? "dofollow" : "nofollow";

		    print <<<END
				<div class="idea">
					<div class="ideaInfo">
						<span class="ideaName"> <a href="$url" rel="$follow">$name</a></span>
						<span class="ideaVoteTotalScore">Scored $comment_vote_score </span>
						<span class="ideaDate">$created</span>
					</div>

					<div class="ideaContent">$comment</div>
					
					<div class="ideaVoteReplyButtons $has_voted_class">
						<span class="ideaVoteButton up"><a href="$direct_to_login_url$crowdio_vote_up_url">$up_vote_icon</a> $comment_upvotes_count </span>
						<span class="ideaVoteButton down"><a href="$direct_to_login_url$crowdio_vote_down_url">$down_vote_icon</a> $comment_downvotes_count </span>
						
						<span class="ideaReplyButton"><a href="$reply_link_url">Reply</a></span>
					</div>
				</div>
END;
		}
	}

	function display_comments()
	{
		global $wpdb;
		// read database comment $wpdb->query('query'); ORDER BY / LIMIT
		$comment_table = CROWDIO_COMMENT_TABLE_NAME;
		$rfi_id = $GLOBALS['post']->ID;
		$firstlevel = $wpdb->get_results("SELECT * FROM $comment_table WHERE rfi_id='$rfi_id' AND (parent_id IS NULL OR parent_id = '0')");
		
		if ($firstlevel)
		{
			// Sorted comments
			$crowdio_db = new CrowdioDatabase();
			print '<div class="firstlevel">';
			
			$sorted_comments_levelone = $crowdio_db->get_ranked_ideas('comment', $rfi_id);


			foreach ($sorted_comments_levelone as $row) {
				$this->display_comment($row, "firstrow");

				$secondlevel = $wpdb->get_results("SELECT * FROM $comment_table WHERE rfi_id = '$rfi_id' AND parent_id = '$row->ID'");
				if ($secondlevel)
				{
					print '<div class="crowdioComment secondlevel">';
					foreach ($secondlevel as $row)
					{
						$this->display_comment($row, "secondlevel");

						$thirdlevel = $wpdb->get_results("SELECT * FROM $comment_table WHERE rfi_id = '$rfi_id' AND parent_id = '$row->ID'");
						if ($thirdlevel)
						{
							print '<div class="crowdioComment thirdlevel">';
							foreach ($thirdlevel as $row)
							{
								$this->display_comment($row, "thirdlevel");
							}
							print '</div><!-- End thirdlevel -->';
						}
					}
					print '</div><!-- End secondlevel -->';
				}
			}
			print '</div>';

		} else
		{
			print '<div class="crowdioNoIdeas">No ideas yet!</div>';
		}
	}

	public function check_comment_submission()
	{	parent::explain("A submit on the comment form was sent, let's make sure we have everything needed.", 3);
		if (!empty($_POST['crowdio_comment_user_id']) &&
		!empty($_POST['crowdio_comment_name']) &&
		!empty($_POST['crowdio_comment_email']) &&
		!empty($_POST['crowdio_rfi_id']) &&
		//!empty($_POST['crowdio_comment_url']) &&
		!empty($_POST['crowdio_comment_content']))
		{	parent::explain("Start adding new comment.", 3);
			$this->add_comment();
		} else
		{	parent::explain("Missing needed data, not going to save. Instead let's find out what happened and alert the user if applicable.", 3);
			if (empty($_POST['crowdio_comment_content']))
			{
				$GLOBALS['crowdio_comment_submit_error'] = 'Required field was left blank.';
				$GLOBALS['crowdio_comment_blank_fields'] = array("crowdio_comment_content");
			}
		}
	}
	
	public function modify_page_content($content)
	{	parent::explain("Putting comment stuff on the page.", 3);
		if (is_single() && $GLOBALS['post']->post_type == 'crowdios')
		{
			$content .= $this->display_comments();
			$content .= $this->display_comment_form();
		}
		
		return $content;
	}
}