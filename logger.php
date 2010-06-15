<?php
interface ISet extends Countable, ArrayAccess, Iterator {
	
	public function add($value);
	
	public function remove( $offset );
	
	public function get( $offset );
	
	public function getAll();
	
	// public function insertAt( $offset, $value );
	
	// public function slice( $offset, $length );
	
	public function addAll( array $values );
	
	public function copyAll( ISet $set );
	
	public function indexOf( $value );
	
	public function each( $fn );
	
}

class Set implements ISet {
	private $dict = array();
	
	public function __construct( array $data = array() ) {
		$this->dict = empty( $data ) ? array() : $data;
	}
	
	public function add( $item ) {
		if ( FALSE === $this->indexOf($item) ) {
			$this->dict [] = $item;
		}
		
		return $this;
	}
	
	public function remove( $offset ) {
		unset( $this->dict[$offset] );
	}
	
	public function get( $offset ) {
		return $this->exists( $offset ) ? $this->dict[$offset] : null;
	}
	
	public function getAll() {
		return $this->dict;
	}
	
	public function addAll( array $values ) {
		foreach( $values as $value ) {
			$this->add( $value );
		}
	}
	
	public function copyAll( ISet $set ) {
		$this->addAll( $set->getAll() );
	}
	
	public function indexOf( $value ) {
		return array_search( $value, $this->dict, TRUE );
	}
	
	public function exists( $key ) {
		return array_key_exists( $key, $this->dict );
	}
	
	/**
	* @Override
	*/
	public function count() {
		return count($this->dict);
	}
	
	/**
	* @Override
	*/
	public function rewind() {
        reset($this->dict);
    }

	/**
	* @Override
	*/
    public function current() {
        return current($this->dict);
    }

	/**
	* @Override
	*/
    public function key() {
        return key($this->dict);
    }

	/**
	* @Override
	*/
    public function next() {
        return next($this->dict);
    }

	/**
	* @Override
	*/
    public function valid() {
        return $this->current() !== false;
    }
    
    public function offsetExists ( $offset ) {
		return $this->exists($offset);
	}
    
	public function offsetGet ( $offset ) {
		return $this->get($offset);
	}
	
	public function offsetSet ( $offset , $value ) {
		$this->dict[$offset] = $value;
	}
	
	public function offsetUnset ( $offset ) {
		$this->remove($offset);
	}
	
	public function clear() {
		$this->dict = array();
	}
	
	public function each( $fn ) {
		$args = array_slice( func_get_args(), 1 );
		
		foreach( $this->getAll() as $item ) {
			call_user_func_array( array( $item, $fn ), $args );
		}
	}
}

function ifsetor( &$check, $otherwise ) {
	return isset($check) ? $check : $otherwise;
}

final class Environment {
	public static $ROOT_CATEGORY = "root";
	public static $CATEGORY_DELIMITER = ".";
	public static $DEFAULT_PATTERN = "[:entry.level] - [:entry.time] - :entry.message \n Throws by :exception.class with code #:exception.code and message :exception.message\n";
	public static $DATE_FORMAT = "H:i:s d-m-Y";
	public static $DEFAULT_BUFFER_SIZE = 1;
	public static $DEFAULT_FLUSH_INTERVAL = 1000;
	
	const COMPILED_VAR_MARKER = "~!";
	
	const LAYOUT_VAR_PATTERN = "/\:(.+?\..+?)[\W]/";
	const LAYOUT_ENTRY_MESSAGE = "entry.message";
	const LAYOUT_ENTRY_TIME = "entry.time";
	const LAYOUT_EXCEPTION_CLASS = "exception.class";
	const LAYOUT_ENTRY_LEVEL = "entry.level";
	const LAYOUT_EXCEPTION_MESSAGE = "exception.message";
	const LAYOUT_EXCEPTION_CODE = "exception.code";
	const LAYOUT_EXCEPTION_STACKTRACE = "exception.stacktrace";
	
	const COMPILED_LAYOUT_EXCEPTION_CODE = 1;
	const COMPILED_LAYOUT_EXCEPTION_STACKTRACE = 2;
	const COMPILED_LAYOUT_EXCEPTION_MESSAGE = 3;
	const COMPILED_LAYOUT_MESSAGE = 4;
	const COMPILED_LAYOUT_TIME = 5;
	const COMPILED_LAYOUT_LEVEL = 6;
	const COMPILED_LAYOUT_EXCEPTION_CLASS = 7;
}

interface ILoggersFactory {
	
	/**
	 * @param ILoggerCategory | String
	 * @return ILogger
	 */
	public function getLogger( $category );
	
	/**
	 * @return ILoggerCategory
	 */
	public function createCategory( $category );
	
	
	/**
	 * @return ILogger
	 */
	public function getRootLogger();
	
	/**
	 * @param ILogger $logger
	 * @return ILogger
	 */
	public function setRootLogger( ILogger $logger );
	
	public function createLoggerEntry( LoggerLevel $level, $message, Exception $exception = null );
	
}

abstract class AbstractLoggersFactory implements ILoggersFactory {
	protected $rootLogger = null;
	
	public function getRootLogger() {
		if ( $this->rootLogger == null ) {
			$this->rootLogger = $this->createRootLogger();
		}
		
		return $this->rootLogger;
	}
	
	public function setRootLogger( ILogger $logger ) {
		$this->rootLogger = $logger;
	}
	
	public function createRootLogger() {
		$logger = $this->createCategory( Environment::$ROOT_CATEGORY );
		$logger->addAppender( new ConsoleAppender() );
		$logger->setEntriesFilter( new StandardFilter( LoggerLevel::all() ) );
		
		return $logger;
	}
	
}

class LoggersFactory extends AbstractLoggersFactory {
	/**
	 * @var ILoggersFactory
	 */
	private static $defaultInstance;
	
	/**
	 * @var array
	 */
	private $loggers = array();
	
	public static function getDefault() {
		if ( self::$defaultInstance == null ) {
			self::$defaultInstance = new LoggersFactory();
		}
		
		return self::$defaultInstance;
	}
	
	public static function setDefault( ILoggersFactory $defaultInstance ) {
		self::$defaultInstance = $defaultInstance;
	}
	
	public function getLogger( $category ) {
		if ( array_key_exists( $category, $this->loggers ) ) {
			return $this->loggers[$category];
		}
		
		$logger = $this->getRootLogger();
		foreach ( explode( Environment::$CATEGORY_DELIMITER, $category ) as $part ) {
			$logger = $logger->createChild( $part );
		}
		
		$this->loggers[$category] = $logger;
		
		return $logger;
	}
	
	public function createLoggerEntry(  LoggerLevel $level, $message, Exception $exception = null  ) {
		return new LoggerEntry( $level, $message, $exception);
	}
	
	public function createCategory( $name ) {
		$category = new Logger($name);
		if ( $this->rootLogger != null ) {
			$category->setParent( $this->rootLogger );
		}
		
		return $category;
	}
}

interface ILoggerCategory {
	/**
	 * @param ILogger $logger
	 * @return ILoggerCategory
	 */
	public function setParent( ILoggerCategory $logger );
	
	/**
	 * @param string $name
	 * @return ILoggerCategory
	 */
	public function setName( $name );
	
	/**
	 * @return string
	 */
	public function getName();
	
	/**
	 * @param ILogAppender $appender
	 * @return ILoggerCategory
	 */
	public function addAppender( ILogAppender $appender );
	
	/**
	 * Get absolute path in inheritance tree (root.com.vio.logger)
	 * @return string
	 */
	public function getPath();
	
	/**
	 * Get parent category for given
	 * 
	 * @return ILoggerCategory
	 */
	public function getParent();
	
	/**
	 * @return ILogAppender
	 */
	public function getAppenders();
	
	/**
	 * @return ILoggerCategory
	public function createChild( $name );
	
	/**
	 * @return array
	 */
	public function getChilds();
	
	/**
	 * @return boolean
	 */
	public function hasChilds();
	
	public function addChild( ILoggerCategory $child );
	
}

abstract class LoggerCategory implements ILoggerCategory {
	/**
	 * @var string
	 */
	private $name;
	
	/**
	 * @var IMap<String, ILoggerCategory>
	 */
	private $childs;
	
	/**
	 * @var ILoggerCategory
	 */
	private $parentCategory;
	
	/**
	 * @var Set
	 */
	private $appenders;
	
	/**
	 * @var string $name
	 */
	public function __construct( $name, ILoggerCategory $parent = null ) {
		$this->name = $name;
		$this->childs = new Set();
		$this->appenders = new Set();
		$this->parentCategory = $parent;
	}
	
	/**
	 * @return ILogAppender
	 */
	public function getAppenders() {
		return count($this->appenders) == 0 && $this->getParent() != null 
					? $this->getParent()->getAppenders() : $this->appenders;
	}
	
	/**
	 * @param ILogAppender $appender
	 * @return ILoggerCategory
	 */
	public function addAppender( ILogAppender $appender ) {
		$this->appenders->add( $appender );
		return $this;
	}
	
	
	public function getPath() {
		$result = "";
		
		$parent = $this;
		do {
			$result = $parent->getName() . $result ;
			if ( $parent->getParent() !== NULL ) {
				$result = "." . $result;
			}
		} while ( NULL !== ( $parent = $parent->getParent() ) );
		
		return $result;
	}
	
	/**
	 * @param string $name
	 * @return ILoggerCategory
	 */
	public function createChild( $name ) {
		$child = $this->childs[$name];
		if ( $child != null ) {
			return $child;
		}
		
		$child = LoggersFactory::getDefault()->createCategory($name);
		$child->setParent( $this );
		
		$this->childs[$name] = $child;
		
		return $child;
	}
	
	/**
	 * @return array
	 */
	public function getChilds() {
		return $this->childs;
	}
	
	/**
	 * @param string $name
	 * @return ILoggerCategory
	 */
	public function removeChild( $name ) {
		$this->childs->remove($name);
		return $this;
	}
	
	/**
	 * @return boolean
	 */
	public function hasChilds() {
		return count( $this->childs ) > 0;
	}
	
	/**
	 * @return ILoggerCategory
	 */
	public function setParent( ILoggerCategory $category ) {
		$this->parentCategory = $category;
		$category->addChild($this);
		return $this;
	}
	
	public function addChild( ILoggerCategory $category ) {
		$this->childs [] = $category;
		
		if ( $category->getParent() != $this ) {
			$category->setParent($this);
		}
	}
	
	/**
	 * @return ILoggerCategory
	 */
	public function getParent() {
		return $this->parentCategory;
	}
	
	public function getName() {
		return $this->name;
	}
	
	public function setName( $name ) {
		$this->name = $name;
	}
	
}
	
interface IEntriesFilter {

	/**
	 * Get minimal level of entry to be approved
	 * 
	 * @return LoggerLevel
	 */
	public function getLevel();

	/**
	 * Check that given level of loggin is applicable by this filter
	 * 
	 * @return boolean
	 */
	public function isApplicable( LoggerLevel $entry );

}

class StandardFilter implements IEntriesFilter {
	/**
	 * @var LoggerLevel
	 */
	private $level;
	
	/**
	 * @param LoggerLevel $level
	 */
	public function __construct( LoggerLevel $level = NULL ) {
		$this->level = $level == NULL ? LoggerLevel::all() : $level;
	}
	
	/**
	 * @return  LoggerLevel
	 */
	public function getLevel() {
		return $this->level;
	}
	
	/**
	 * @param LoggerLevel
	 * @return IEntriesFilter
	 */
	public function setLevel( LoggerLevel $level ) {
		$this->level = $level;
		return $this;
	}
	
	public function isApplicable( LoggerLevel $level ) {
		return !( $level == null && $level->isHigher( $this->getLevel() ) );
	}
}	

interface ILogger extends ILoggerCategory {
	
	/**
	 * @param string $message
	 * @param Exception $exception
	 * @return void
	 */
	public function info( $message, Exception $exception = null );
	
	/**
	 * @param string $message
	 * @param Exception $exception
	 * @return void
	 */
	public function error( $message, Exception $exception = null );
	
	/**
	 * @param string message
	 * @param Exception $exception
	 * @return void
	 */
	public function warn( $message, Exception $exception = null );
	
	/**
	 * @param LoggerLevel $level
	 * @param string message
	 * @param Exception $exception
	 * @return void
	 */
	public function log( LoggerLevel $level, $message, Exception $exception = null );
	
	/**
	 * Set filter to prevent some entries from being logged
	 * 
     * @param IEntriesFilter $filter
	 * @return ILogger
	 */
	public function setEntriesFilter( IEntriesFilter $filter );
	
	/**
	 * @return IEntriesFilter
	 */
	public function getEntriesFilter();
}

class Logger extends LoggerCategory {
	private $entriesFilter;
	
	public function __construct( $name, ILoggerCategory $parent = NULL, IEntriesFilter $filter = NULL ) {
		parent::__construct($name, $parent);
		
		$this->entriesFilter = new StandardFilter();
	}
	
	public function setEntriesFilter( IEntriesFilter $filter ) {
		$this->entriesFilter = $filter;
		return $this;
	}
	
	public function getEntriesFilter() {
		return $this->entriesFilter == null && $this->getParent() != null ? $this->getParent()->getEntriesFilter() : $this->entriesFilter;
	}
	
	public function log( LoggerLevel $level, $message, Exception $exception = NULL ) {
		if ( $this->getEntriesFilter() == NULL || $this->getEntriesFilter()->isApplicable( $level ) ) {
			$this->getAppenders()->each( "append", LoggersFactory::getDefault()->createLoggerEntry( $level, $message, $exception ) );
		}
	}
	
	public function info( $message, Exception $exception = null ) {
		$this->log( LoggerLevel::info(), $message, $exception );
	}
	
	public function error( $message, Exception $exception = null ) {
		$this->log( LoggerLevel::error(), $message, $exception );
	}
	
	public function warn( $message, Exception $exception = null ) {
		$this->log( LoggerLevel::warning(), $message, $exception );
	}
	
}
	
interface IAppenderLayout {	
	
	/**
	 * @param IEntry $entry
	 * @return string
	 */
	public function render( ILoggerEntry $entry );
	
	/**
	 * @return string
	 */
	public function setLayout( $layout );
	
	/**
	 * @return string
	 */
	public function getLayout();
	
}

class StandardLayout implements IAppenderLayout {
	/**
	 * Original layout taken from constructor or setLayout($layout) mutator
	 * @var string $layout
	 */
	private $layout;
	
	/**
	 * Processed version of $layout
	 * @var string $compiled_layout
	 */
	private $compiled_layout;
	
	/**
	 * @param string $layout
	 */
	public function __construct( $layout = NULL ) {
		$this->setLayout( ifsetor( $layout, Environment::$DEFAULT_PATTERN ) );
	}
	
	/**
	 * @TODO rework rendering to language injection and compiling layout to AST
	 * @return String
	 */
	public function render( ILoggerEntry $entry ) {
		$result = $this->getCompiledLayout();
		
		$pos = 0;
		while( $pos < strlen($result) && FALSE !== ( $pos = @strpos( $result, Environment::COMPILED_VAR_MARKER, $pos ) ) ) {
			$marker = substr( $result, $pos + strlen(Environment::COMPILED_VAR_MARKER), 1 );

			$result = str_replace( Environment::COMPILED_VAR_MARKER . $marker, $this->getMarkerValue( $entry, $marker), $result );						
		}
		
		return $result;
	}
	
	protected function getIdForMarker( $value ) {
		switch ( $value ) {
			case Environment::LAYOUT_EXCEPTION_CLASS:
				return Environment::COMPILED_LAYOUT_EXCEPTION_CLASS;
			break;
			case Environment::LAYOUT_ENTRY_MESSAGE:
				return Environment::COMPILED_LAYOUT_MESSAGE;
			break;
			case Environment::LAYOUT_ENTRY_TIME:
				return Environment::COMPILED_LAYOUT_TIME;
			break;
			case Environment::LAYOUT_ENTRY_LEVEL:
				return Environment::COMPILED_LAYOUT_LEVEL;
			break;
			case Environment::LAYOUT_EXCEPTION_MESSAGE:
				return Environment::COMPILED_LAYOUT_EXCEPTION_MESSAGE;
			break;
			case Environment::LAYOUT_EXCEPTION_CODE:
				return Environment::COMPILED_LAYOUT_EXCEPTION_CODE;
			break;
			case Environment::LAYOUT_EXCEPTION_STACKTRACE:
				return Environment::COMPILED_LAYOUT_EXCEPTION_STACKTRACE;
			break;
			default:
				throw new IllegalArgumentException();
		}
	}
	
	protected function getMarkerValue( ILoggerEntry $entry, $mark ) {
		switch( $mark ) {
			case Environment::COMPILED_LAYOUT_EXCEPTION_CLASS:
				return get_class( $entry->getException() );
			break;
			case Environment::COMPILED_LAYOUT_EXCEPTION_CODE:
				return $entry->getException() != null ? $entry->getException()->getCode() : "undefined code";
			break;
			case Environment::COMPILED_LAYOUT_EXCEPTION_MESSAGE:
				return $entry->getException() != null ? $entry->getException()->getMessage() : "undefined exception";
			break;
			case Environment::COMPILED_LAYOUT_EXCEPTION_STACKTRACE:
				return $entry->getException() != null ? $entry->getException()->getStackTrace() : "";
			break;
			case Environment::COMPILED_LAYOUT_TIME:
				return date( Environment::$DATE_FORMAT, $entry->getTime() );
			break;
			case Environment::COMPILED_LAYOUT_LEVEL:
				return LoggerLevel::valueOf( $entry->getLevel() );
			break;
			case Environment::COMPILED_LAYOUT_MESSAGE:
				return $entry->getMessage();
			break;
			default:
				throw new IllegalArgumentException();
		}
	}
	
	protected function getCompiledLayout() {
		return $this->compiled_layout;
	}
	
	public function setLayout( $layout ) {
		$this->layout = $layout;
		$this->compiled_layout = $this->compileLayout( $layout );
		return $this;
	}
	
	public function getLayout() {
		return $this->layout;
	}
	
	protected function compileLayout( $layout ) {
		if ( preg_match_all( Environment::LAYOUT_VAR_PATTERN, $layout, &$matches ) ) {
			foreach( $matches[1] as $var ) {
				$layout = str_replace( ":" . $var, Environment::COMPILED_VAR_MARKER . $this->getIdForMarker( $var ), $layout );
			}
		}
		
		
		return $layout;
	}
}


interface ILoggerEntry {
	
	/**
	 * @param LoggerLevel $level
	 * @return void
	 */
	public function setLevel( LoggerLevel $level );
	
	/**
	 * @return LoggerLevel
	 */
	public function getLevel();
	
}

class LoggerEntry implements ILoggerEntry {
	private $exception;
	private $message;
	private $time;
	private $level;
	
	public function LoggerEntry( LoggerLevel $level, $message, Exception $exception = NULL ) {
		$this->level = $level;
		$this->message = $message;
		$this->exception = $exception;
		$this->time = time();
	}
	
	public function getException() {
		return $this->exception;
	}
	
	public function getMessage() {
		return $this->message;
	}
	
	public function getTime() {
		return $this->time;
	}
	
	public function getLevel() {
		return $this->level;
	}
	
	public function setLevel( LoggerLevel $level ) {
		$this->level = $level;
		return $this;
	}
}
	
	

interface ILogAppender {
		
	/**
	 * Put new entry to the flush queue
	 * @param ILoggerEntry
	 * @return ILogAppender
	 */
	public function append( ILoggerEntry $entry );
	
	/**
	 * @return IEntryRenderer
	 */
	public function getLayout();
	
	/**
	 * @param IEntryRenderer $renderer
	 * @return ILoggerCategory
	 */
	public function setLayout( IAppenderLayout $renderer );
	
	
	/**
	 * @param IEntriesFilter $filter
	 * @return ILogAppender
	 */
	public function setEntriesFilter( IEntriesFilter $filter );
	
	/**
	 * @return IEntriesFilter
	 */
	public function getEntriesFilter();
	
}

interface IBufferedLogAppender extends ILogAppender {
	/**
	 * @param LogAppenderMode $mode
	 * @return ILogAppender
	 */
	public function setMode( LogAppenderMode $mode );
	
	/**
	 * @return LogAppenderMode
	 */
	public function getMode();
	
	/**
	 * Set interval before appender attempts to flush entries
	 * @param long $milliseconds
	 */
	public function setFlushInterval( $milliseconds );
	
	/**
	 * Start flushing session intermediate
	 */
	public function flush();

	/**
	 * @return long
	 */
	public function getFlushInterval();
	
	/**
	 * Return maximal buffered entries to initiate commit
	 * @return int
	 */
	public function getBufferSize();
	
	/**
	 * Set maximal buffered entries count to initiate commit
	 * @return int
	 */
	public function setBufferSize( $value ); 
	
	/**
	 * Get actual entries buffer size
	 */
	public function getActualBufferSize();
}

abstract class AbstractLogAppender implements ILogAppender {
	/**
	 * @var IAppenderLayout
	 */
	private $layout;
	
	/**
	 * @var IEntriesFilter
	 */
	private $entriesFilter;
	
	/** 
	 * @param IAppenderLayour $layout
	 */
	public function __construct( IAppenderLayout $layout ) {
		$this->layout = $layout;
	}

	/**
	 * @param IAppenderLayout $layout
	 * @return ILogAppender
	 */
	public function setLayout( IAppenderLayout $renderer ) {
		$this->layout = $layout;
		return $this;
	}
	
	/**
	 * @return IAppenderLayout
	 */
	public function getLayout() {
		return $this->layout;
	}
	
	/**
	 * @return ILogAppender
	 */
	public function setEntriesFilter( IEntriesFilter $filter ) {
		$this->entriesFilter = $filter;
		return $this;
	}
	
	/**
	 * @return IEntriesFilter
	 */
	public function getEntriesFilter() {
		return $this->entriesFilter;
	}

}

abstract class AbstractBufferedLogAppender extends AbstractLogAppender {
	/**
	 * @var int
	 */
	private $bufferSize;
	
	/**
	 * @var int
	 */
	private $flushInterval;
	
	/**
	 * @var long
	 */
	private $lastFlushTime;
	
	/**
	 * @var LogAppenderMode
	 */
	private $appendingMode;
	
	/**
	 * @var ISet
	 */
	private $buffer;
	
	public function __construct( IAppenderLayout $layout, LogAppenderMode $mode ) {
		parent::__construct($layout);
		
		$this->flushInterval = Environment::$DEFAULT_FLUSH_INTERVAL;
		$this->bufferSize = Environment::$DEFAULT_BUFFER_SIZE;
		$this->appendingMode = $mode;
		$this->buffer = new Set();
	}
	
	/**
	 * @param int $value
	 * @return IBufferedLogAppender
	 */
	public function setBufferSize( $value ) {
		$this->bufferSize = $value;
		return $this;
	}
	
	/**
	 * @var int
	 */
	public function getBufferSize() {
		return $this->bufferSize;
	}
	
	protected function getBuffer() {
		return $this->buffer;
	}
	
	/**
	 * @param int $value
	 * @return IBufferedLogAppender
	 */
	public function setFlushInterval( $value ) {
		$this->flushInterval = $value;
		return $this;
	}
	
	/**
	 * @return int
	 */
	public function getFlushInterval() {
		return $this->flushInterval;
	}
	
	/**
	 * @param LogAppenderMode $mode
	 * return IBufferedLogAppender
	 */
	public function setMode( LogAppenderMode $mode ) {
		$this->appendingMode = $mode;
		return $this;
	}
	
	/**
	 * @return LogAppenderMode
	 */
	public function getMode() {
		return $this->appendingMode;
	}
	
	public function getActualBufferSize() {
		return count($this->getBuffer());
	}
	
	public function getLastFlushTime() {
		return $this->lastFlushTime;
	}
	
	protected function updateLastFlushTime() {
		$this->lastFlushTime = time();
	}
	
	protected function invalidate() {
		$result = true;
		if ( $this->getBufferSize() <= $this->getActualBufferSize() ) {
			$result = false;
		} else if (  ( time() - $this->getLastFlushTime() ) >= $this->getFlushInterval() ) {
			$result = false;
		}
		
		$this->flush();
		$this->updateLastFlushTime();
		$this->getBuffer()->clear();
		
		return $result;
	}
	
}

class ConsoleAppender extends AbstractLogAppender {
	
	public function __construct( IAppenderLayout $layout = NULL ) {
		parent::__construct( new StandardLayout() );
	}
	
	public function append( ILoggerEntry $entry ) {
		if ( $this->getEntriesFilter() == NULL || $this->getEntriesFilter()->isApplicable($entry) ) {
			echo $this->getLayout()->render( $entry );
		}
	}

}

class FileAppender extends AbstractBufferedLogAppender {
	/**
	 * @var String
	 */
	private $logPath;
	
	public function append( ILoggerEntry $entry ) {
		if ( $this->getEntriesFilter() == NULL || $this->getEntriesFilter()->isApplicable($entry) ) {
			$this->buffer->add( $entry );
			
			$this->invalidate();
		}
	}
	
	public function __construct( $logPath, LogAppenderMode $mode = null ) {
		parent::__construct( new StandardLayout(), ifsetor( $mode, LogAppenderMode::append() ) );
		
		$this->buffer = new Set();
		$this->logPath = $logPath;
	}
	
	/**
	 * @param string $path
	 * @return IBufferedLogger
	 */
	public function setLogPath( $path ) {
		$this->logPath = $path;
		return $this;
	}
	
	/**
	 * @return string
	 */
	public function getLogPath() {
		return $this->logPath;
	}
	
	public function flush() {	
		$fp = fopen( $this->getLogPath(), LogAppenderMode::valueOf( $this->getMode() ) );
		if ( FALSE === $fp ) {
			throw new LogAppenderException();
		}
		
		flock($fp, LOCK_EX );
		
		foreach( $this->buffer as $entry ) {
			fwrite( $fp, $this->getLayout()->render( $entry ) );
		}
		
		flock($fp, LOCK_UN);
		fclose($fp);
	}
	
}

class LogAppenderMode {
	private $value;
	
	/**
	 * @param string $value
	 */
	public function __constructor( $value ) {
		$this->value = $value;
	}
	
	/**
	 * After appender flush session the data saved from previous commit will be cleaned
	 * @var LogAppenderMode
	 */
	public static function write() {
		return new LogAppenderMode(1);
	}
	
	/**
	 * Data flushed by appender will be adopted to the end of history file
	 * @var LogAppenderMode
	 */
	public static function append() {
		return new LogAppenderMode(2);
	}
	
	/**
	 * @return string
	 */
	public static function valueOf( LogAppenderMode $mode ) {
		switch( $mode ) {
			case self::append():
				return "a+";
			break;
			case self::write():
				return "w+";
			break;
			default:
				throw new IllegalArgumentException("Wrong appending mode object given");
		}
	}
}

/**
 * @enum
 */
class LoggerLevel {
	private $level;
	
	private static $LABELS = array(
		1 => "INFO",
		2 => "ERROR",
		3 => "WARNING",
		4 => "DEBUG"
	);
	
	public static function all() {
		return new LoggerLevel(0);
	}
	
	/**
	 * Category for all informational messages
	 * @var LoggerLevel
	 */
	public static function info() { 
		return new LoggerLevel(3);
	}
	
	/**
	 * Category for all error messages
	 * @var LoggerLevel
	 */
	public static function error() {
		return new LoggerLevel(1);
	}
	
	/**
	 * Category for all warning type messages
	 * @var LoggerLevel
	 */
	public static function warning() {
		 return new LoggerLevel(2); 
	}
	
	/**
	 * Category for debug messages
	 * @var LoggerLevel
	 */
	public static function debug() {
		return new LoggerLevel(4);
	}
	
	/**
	 * @param int level
	 */
	private function __construct( $level ) {
		$this->level = $level;
	}
	
	public function isHigher( LoggerLevel $level ) {
		return $level->level > $this->level;
	}
	
	public static function valueOf( LoggerLevel $level ) {
		return self::$LABELS[$level->level];
	}

}

class IllegalArgumentException extends Exception {}

class LogAppenderException extends Exception {}

$logger = LoggersFactory::getDefault()->getLogger("com.vio.*");
$logger->addAppender( new FileAppender("main.log") );
$logger->info("Afla!");


try {
	throw new IllegalArgumentException("Afla!");
} catch ( Exception $e ) {
	$logger->error( $e->getMessage(), $e );
}
