<?php

namespace SMW\SQLStore;

use SMW\CompatibilityMode;
use Onoi\MessageReporter\MessageReporter;
use Onoi\MessageReporter\MessageReporterAware;
use Onoi\MessageReporter\MessageReporterFactory;
use Hooks;

/**
 * @private
 *
 * @license GNU GPL v2+
 * @since 2.5
 *
 * @author mwjames
 */
class Installer implements MessageReporter, MessageReporterAware {

	/**
	 * MessageReport option
	 */
	const OPT_MESSAGEREPORTER = 'installer.messagereporter';

	/**
	 * @var TableSchemaManager
	 */
	private $tableSchemaManager;

	/**
	 * @var TableBuilder
	 */
	private $tableBuilder;

	/**
	 * @var TableIntegrityExaminer
	 */
	private $tableIntegrityExaminer;

	/**
	 * @var MessageReporter
	 */
	private $messageReporter;

	/**
	 * @var boolean
	 */
	private $isExtensionSchemaUpdate = false;

	/**
	 * @since 2.5
	 *
	 * @param TableSchemaManager $tableSchemaManager
	 * @param TableBuilder $tableBuilder
	 * @param TableIntegrityExaminer $tableIntegrityExaminer
	 */
	public function __construct( TableSchemaManager $tableSchemaManager, TableBuilder $tableBuilder, TableIntegrityExaminer $tableIntegrityExaminer ) {
		$this->tableSchemaManager = $tableSchemaManager;
		$this->tableBuilder = $tableBuilder;
		$this->tableIntegrityExaminer = $tableIntegrityExaminer;
	}

	/**
	 * @since 2.5
	 *
	 * @param boolean $isExtensionSchemaUpdate
	 */
	public function isExtensionSchemaUpdate( $isExtensionSchemaUpdate ) {
		$this->isExtensionSchemaUpdate = (bool)$isExtensionSchemaUpdate;
	}

	/**
	 * @see MessageReporterAware::setMessageReporter
	 *
	 * @since 2.5
	 *
	 * @param MessageReporter $messageReporter
	 */
	public function setMessageReporter( MessageReporter $messageReporter ) {
		$this->messageReporter = $messageReporter;
	}

	/**
	 * @since 2.5
	 *
	 * @param boolean $verbose
	 */
	public function install( $verbose = true ) {

		// If for some reason the enableSemantics was not yet enabled
		// still allow to run the tables create in order for the
		// setup to be completed
		if ( CompatibilityMode::extensionNotEnabled() ) {
			CompatibilityMode::enableTemporaryCliUpdateMode();
		}

		$messageReporter = $this->newMessageReporter( $verbose );

		$messageReporter->reportMessage( "\nSelected storage engine is \"SMWSQLStore3\" (or an extension thereof)\n" );
		$messageReporter->reportMessage( "\nSetting up standard database configuration for SMW ...\n\n" );

		$this->tableBuilder->setMessageReporter(
			$messageReporter
		);

		$this->tableIntegrityExaminer->setMessageReporter(
			$messageReporter
		);

		foreach ( $this->tableSchemaManager->getTables() as $table ) {
			$this->tableBuilder->create( $table );
		}

		$this->tableIntegrityExaminer->checkOnPostCreation( $this->tableBuilder );

		Hooks::run( 'SMW::SQLStore::AfterCreateTablesComplete', array( $this->tableBuilder ) );

		$messageReporter->reportMessage(
			"\nDatabase initialized completed.\n"  . ( $this->isExtensionSchemaUpdate ? "\n" : '' )
		);

		return true;
	}

	/**
	 * @since 2.5
	 *
	 * @param boolean $verbose
	 */
	public function uninstall( $verbose = true ) {

		$messageReporter = $this->newMessageReporter( $verbose );

		$messageReporter->reportMessage( "\nSelected storage engine is \"SMWSQLStore3\" (or an extension thereof)\n" );
		$messageReporter->reportMessage( "\nDeleting all database content and tables generated by SMW ...\n\n" );

		$this->tableBuilder->setMessageReporter(
			$messageReporter
		);

		foreach ( $this->tableSchemaManager->getTables() as $table ) {
			$this->tableBuilder->drop( $table );
		}

		Hooks::run( 'SMW::SQLStore::AfterDropTablesComplete', array( $this->tableBuilder ) );

		$messageReporter->reportMessage( "\nStandard and auxiliary tables with corresponding data have been removed successfully.\n" );

		return true;
	}

	/**
	 * @since 2.5
	 *
	 * @param string $message
	 */
	public function reportMessage( $message ) {
		ob_start();
		print $message;
		ob_flush();
		flush();
		ob_end_clean();
	}

	private function newMessageReporter( $verbose = true ) {

		if ( !$verbose ) {
			$messageReporter = MessageReporterFactory::getInstance()->newNullMessageReporter();
		} else {
			$messageReporter = MessageReporterFactory::getInstance()->newObservableMessageReporter();
			$messageReporter->registerReporterCallback( array( $this, 'reportMessage' ) );
		}

		return $this->messageReporter !== null ? $this->messageReporter : $messageReporter;
	}

}
