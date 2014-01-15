<?php
/**
 * RESTfulAPI Test suite DataObjects
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package RESTfulAPI
 * @subpackage Tests
 */
class ApiTest_Library extends DataObject
{
  private static $db = array (
    'Name' => 'Varchar(255)'
  );

  private static $many_many = array(
    'Books' => 'ApiTest_Book'
  );
}

class ApiTest_Book extends DataObject
{
  private static $db = array (
    'Title' => 'Varchar(255)',
    'Pages' => 'Int'
  );

  private static $has_one = array(
    'Author' => 'ApiTest_Author'
  );

  private static $belongs_many_many = array(
    'Libraries' => 'ApiTest_Library'
  );
}

class ApiTest_Author extends DataObject
{
  private static $db = array (
    'Name' => 'Varchar(255)',
    'IsMan' => 'Boolean'
  );

  private static $has_many = array(
    'Books' => 'ApiTest_Book'
  );
}