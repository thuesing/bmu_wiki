<?php
/**
 * A special page holding a form that allows the user to create a semantic
 * property.
 *
 * @author Yaron Koren
 * @author Sanyam Goyal
 * @file
 * @ingroup SF
 */

/**
 * @ingroup SFSpecialPages
 */
class SFCreateClass extends SpecialPage {
	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct( 'CreateClass', 'createclass' );
	}

	static function addJavascript( $numStartingRows ) {
		global $wgOut;

		SFUtils::addJavascriptAndCSS();

		$jsText =<<<END
<script>
var rowNum = $numStartingRows;
function createClassAddRow() {
	rowNum++;
	newRow = jQuery('#starterRow').clone().css('display', '');
	newHTML = newRow.html().replace(/starter/g, rowNum);
	newRow.html(newHTML);
	jQuery('#mainTable').append(newRow);
}

function disableFormAndCategoryInputs() {
	if (jQuery('#template_multiple').attr('checked')) {
		jQuery('#form_name').attr('disabled', 'disabled');
		jQuery('label[for="form_name"]').css('color', 'gray').css('font-style', 'italic');
		jQuery('#category_name').attr('disabled', 'disabled');
		jQuery('label[for="category_name"]').css('color', 'gray').css('font-style', 'italic');
	} else {
		jQuery('#form_name').removeAttr('disabled');
		jQuery('label[for="form_name"]').css('color', '').css('font-style', '');
		jQuery('#category_name').removeAttr('disabled');
		jQuery('label[for="category_name"]').css('color', '').css('font-style', '');
	}
}

</script>

END;
		$wgOut->addScript( $jsText );
	}

	static function createAllPages() {
		global $wgOut, $wgRequest, $wgUser;

		$template_name = trim( $wgRequest->getVal( "template_name" ) );
		$template_multiple = $wgRequest->getBool( "template_multiple" );
		// If this is a multiple-instance template, there
		// shouldn't be a corresponding form or category.
		if ( $template_multiple ) {
			$form_name = null;
			$category_name = null;
		} else {
			$form_name = trim( $wgRequest->getVal( "form_name" ) );
			$category_name = trim( $wgRequest->getVal( "category_name" ) );
		}
		if ( $template_name === '' || ( !$template_multiple && ( $form_name === '' || $category_name === '' ) ) ) {
			$wgOut->addWikiMsg( 'sf_createclass_missingvalues' );
			return;
		}
		$fields = array();
		$jobs = array();
		// Cycle through all the rows passed in.
		for ( $i = 1; $wgRequest->getCheck( "field_name_$i" ); $i++ ) {
			// go through the query values, setting the appropriate local variables
			$property_name = trim( $wgRequest->getVal( "property_name_$i" ) );
			$field_name = trim( $wgRequest->getVal( "field_name_$i" ) );
			$property_type = $wgRequest->getVal( "property_type_$i" );
			$allowed_values = $wgRequest->getVal( "allowed_values_$i" );
			$is_list = $wgRequest->getCheck( "is_list_$i" );
			// Create an SFTemplateField object based on these
			// values, and add it to the $fields array.
			$field = SFTemplateField::create( $field_name, $field_name, $property_name, $is_list );
			$fields[] = $field;

			// Create the property, and make a job for it.
			if ( !empty( $property_name ) ) {
				$full_text = SFCreateProperty::createPropertyText( $property_type, '', $allowed_values );
				$property_title = Title::makeTitleSafe( SMW_NS_PROPERTY, $property_name );
				$params = array();
				$params['user_id'] = $wgUser->getId();
				$params['page_text'] = $full_text;
				$jobs[] = new SFCreatePageJob( $property_title, $params );
			}
		}

		// Create the template, and save it (might as well save
		// one page, instead of just creating jobs for all of them).
		$full_text = SFTemplateField::createTemplateText( $template_name, $fields, null, $category_name, null, null, null );
		$template_title = Title::makeTitleSafe( NS_TEMPLATE, $template_name );
		$template_article = new Article( $template_title, 0 );
		$edit_summary = '';
		$template_article->doEdit( $full_text, $edit_summary );

		// Create the form, and make a job for it.
		$form_template = SFTemplateInForm::create( $template_name, '', false );
		$form_templates = array( $form_template );
		$form = SFForm::create( $form_name, $form_templates );
		$full_text = $form->createMarkup();
		$form_title = Title::makeTitleSafe( SF_NS_FORM, $form_name );
		$params = array();
		$params['user_id'] = $wgUser->getId();
		$params['page_text'] = $full_text;
		$jobs[] = new SFCreatePageJob( $form_title, $params );

		// Create the category, and make a job for it.
		$full_text = SFCreateCategory::createCategoryText( $form_name, $category_name, '' );
		$category_title = Title::makeTitleSafe( NS_CATEGORY, $category_name );
		$params = array();
		$params['user_id'] = $wgUser->getId();
		$params['page_text'] = $full_text;
		$jobs[] = new SFCreatePageJob( $category_title, $params );
		Job::batchInsert( $jobs );

		$wgOut->addWikiMsg( 'sf_createclass_success' );
	}

	function execute( $query ) {
		global $wgOut, $wgRequest, $wgUser, $sfgScriptPath;
		global $wgLang, $smwgContLang;

		// Check permissions.
		if ( !$wgUser->isAllowed( 'createclass' ) ) {
			$this->displayRestrictionError();
			return;
		}

		$this->setHeaders();
		$wgOut->addExtensionStyle( $sfgScriptPath . "/skins/SemanticForms.css" );
		$numStartingRows = 5;
		self::addJavascript( $numStartingRows );

		$createAll = $wgRequest->getCheck( 'createAll' );
		if ( $createAll ) {
			self::createAllPages();
			return;
		}

		$datatypeLabels = $smwgContLang->getDatatypeLabels();

		// Make links to all the other 'Create...' pages, in order to
		// link to them at the top of the page.
		$creation_links = array();
		$creation_links[] = SFUtils::linkForSpecialPage( 'CreateProperty' );
		$creation_links[] = SFUtils::linkForSpecialPage( 'CreateTemplate' );
		$creation_links[] = SFUtils::linkForSpecialPage( 'CreateForm' );
		$creation_links[] = SFUtils::linkForSpecialPage( 'CreateCategory' );
		$form_name_label = wfMessage( 'sf_createclass_nameinput' )->text();
		$category_name_label = wfMessage( 'sf_createcategory_name' )->text();
		$field_name_label = wfMessage( 'sf_createtemplate_fieldname' )->text();
		$list_of_values_label = wfMessage( 'sf_createclass_listofvalues' )->text();
		$property_name_label = wfMessage( 'sf_createproperty_propname' )->text();
		$type_label = wfMessage( 'sf_createproperty_proptype' )->text();
		$allowed_values_label = wfMessage( 'sf_createclass_allowedvalues' )->text();

		$text = '<form action="" method="post">' . "\n";
		$text .= "\t" . Html::rawElement( 'p', null, wfMessage( 'sf_createclass_docu', $wgLang->listToText( $creation_links ) )->text() ) . "\n";
		$templateNameLabel = wfMessage( 'sf_createtemplate_namelabel' )->text();
		$templateNameInput = Html::input( 'template_name', null, 'text', array( 'size' => 30 ) );
		$text .= "\t" . Html::rawElement( 'p', null, $templateNameLabel . ' ' . $templateNameInput ) . "\n";
		$templateInfo = SFCreateTemplate::printTemplateStyleInput( 'template_format' );
		$templateInfo .= Html::rawElement( 'p', null,
			Html::element( 'input', array(
				'type' => 'checkbox',
				'name' => 'template_multiple',
				'id' => 'template_multiple',
				'onclick' => "disableFormAndCategoryInputs()",
			) ) . ' ' . wfMessage( 'sf_createtemplate_multipleinstance' )->text() ) . "\n";
		$text .= Html::rawElement( 'blockquote', null, $templateInfo );

		$text .= "\t" . Html::rawElement( 'p', null, Html::element( 'label', array( 'for' => 'form_name' ), $form_name_label ) . ' ' . Html::element( 'input', array( 'size' => '30', 'name' => 'form_name', 'id' => 'form_name' ), null ) ) . "\n";
		$text .= "\t" . Html::rawElement( 'p', null, Html::element( 'label', array( 'for' => 'category_name' ), $category_name_label ) . ' ' . Html::element( 'input', array( 'size' => '30', 'name' => 'category_name', 'id' => 'category_name' ), null ) ) . "\n";
		$text .= "\t" . Html::element( 'br', null, null ) . "\n";
		$property_label = wfMessage( 'smw_pp_type' )->text();
		$text .= <<<END
	<div>
		<table id="mainTable" style="border-collapse: collapse;">
		<tr>
			<th colspan="3" />
			<th colspan="3" style="background: #ddeebb; padding: 4px;">$property_label</th>
		</tr>
		<tr>
			<th colspan="2">$field_name_label</th>
			<th style="padding: 4px;">$list_of_values_label</th>
			<th style="background: #eeffcc; padding: 4px;">$property_name_label</th>
			<th style="background: #eeffcc; padding: 4px;">$type_label</th>
			<th style="background: #eeffcc; padding: 4px;">$allowed_values_label</th>
		</tr>

END;
		// Make one more row than what we're displaying - use the
		// last row as a "starter row", to be cloned when the
		// "Add another" button is pressed.
		for ( $i = 1; $i <= $numStartingRows + 1; $i++ ) {
			if ( $i == $numStartingRows + 1 ) {
				$rowString = 'id="starterRow" style="display: none"';
				$n = 'starter';
			} else {
				$rowString = '';
				$n = $i;
			}
			$text .= <<<END
		<tr $rowString style="margin: 4px;">
			<td>$n.</td>
			<td><input type="text" size="25" name="field_name_$n" /></td>
			<td style="text-align: center;"><input type="checkbox" name="is_list_$n" /></td>
			<td style="background: #eeffcc; padding: 4px;"><input type="text" size="25" name="property_name_$n" /></td>
			<td style="background: #eeffcc; padding: 4px;">

END;
			$typeDropdownBody = '';
			foreach ( $datatypeLabels as $label ) {
				$typeDropdownBody .= "\t\t\t\t<option>$label</option>\n";
			}
			$text .= "\t\t\t\t" . Html::rawElement( 'select', array( 'name' => "property_type_$n" ), $typeDropdownBody ) . "\n";
			$text .= <<<END
			</td>
			<td style="background: #eeffcc; padding: 4px;"><input type="text" size="25" name="allowed_values_$n" /></td>

END;
		}
		$text .= <<<END
		</tr>
		</table>
	</div>

END;
		$add_another_button = Html::element( 'input',
			array(
				'type' => 'button',
				'value' => wfMessage( 'sf_formedit_addanother' )->text(),
				'onclick' => "createClassAddRow()"
			)
		);
		$text .= Html::rawElement( 'p', null, $add_another_button ) . "\n";
		// Set 'title' as hidden field, in case there's no URL niceness
		$cc = $this->getTitle();
		$text .= Html::hidden( 'title', SFUtils::titleURLString( $cc ) );
		$text .= Html::element( 'input',
			array(
				'type' => 'submit',
				'name' => 'createAll',
				'value' => wfMessage( 'sf_createclass_create' )->text()
			)
		);
		$text .= "</form>\n";
		$wgOut->addHTML( $text );
	}
}
