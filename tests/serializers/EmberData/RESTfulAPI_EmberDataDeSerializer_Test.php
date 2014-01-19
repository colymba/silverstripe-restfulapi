<?php
/**
 * EmberData DeSerializer Test suite
 * 
 * @author  Thierry Francois @colymba thierry@colymba.com
 * @copyright Copyright (c) 2013, Thierry Francois
 * 
 * @license http://opensource.org/licenses/BSD-3-Clause BSD Simplified
 * 
 * @package RESTfulAPI
 * @subpackage Tests
 */
class RESTfulAPI_EmberDataDeSerializer_Test extends RESTfulAPI_Tester
{
  protected $extraDataObjects = array(
    'ApiTest_Author',
    'ApiTest_Book',
    'ApiTest_Library'
  );

  protected function getDeSerializer()
  {
    $injector     = new Injector();
    $deserializer = new RESTfulAPI_BasicDeSerializer();

    $injector->inject($deserializer);

    return $deserializer;
  }


  /* **********************************************************
   * TESTS
   * */


  /**
   * Checks payload deserialization
   */
  public function testDeserialize()
  {
    $deserializer = $this->getDeSerializer();
    $json = json_encode(array('Name' => 'Some name'));    
    $result = $deserializer->deserialize($json);

    $this->assertTrue(
      is_array($result),
      "Basic DeSerialize should return an array"
    );

    $this->assertEquals(
      "Some name",
      $result['Name'],
      "Basic DeSerialize should not change values"
    );
  }


  /**
   * Checks payload column/class names unformatting
   */
  public function testUnformatName()
  {
    $deserializer = $this->getDeSerializer();

    $column = 'Name';
    $class  = 'apiTest_Author';

    $this->assertEquals(
      $column,
      $deserializer->unformatName($column),
      "Basic DeSerialize should not change name formatting"
    );

    $this->assertEquals(
      'ApiTest_Author',
      $deserializer->unformatName($class),
      "Basic DeSerialize should return ucfirst class name"
    );
  }
}