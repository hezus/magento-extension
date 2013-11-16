<?php

/**
 * Abstract Class Model
 *
 * @category  Jirafe
 * @package   Jirafe_Analytics
 * @copyright Copyright (c) 2013 Jirafe, Inc. (http://jirafe.com/)
 * @author    Richard Loerzel (rloerzel@lyonscg.com)
 */

abstract class Jirafe_Analytics_Model_Abstract extends Mage_Core_Model_Abstract
{
    protected $_rootMap = null;
    
    protected $_mappedFields = null;
    
    /**
     * Get API to Magento field map array
     *
     * @return array
     * @throws Exception
     */
    
    protected function _getRootMap()
    {
        try {
            /**
             * Pull map from cache or generate new map
             */
            $cache = Mage::app()->getCache();
            
            if ( !$rootMap = $cache->load('jirafe_analytics_map') ) {
                $rootMap = json_encode( Mage::getSingleton('jirafe_analytics/map')->getArray() );
                $cache->save( $rootMap, 'jirafe_analytics_map', array('jirafe_analytics_map'), null);
            }
            
            return json_decode($rootMap,true);
            
        } catch (Exception $e) {
            Mage::throwException('FIELD MAPPING ERROR Jirafe_Analytics_Model_Abstract::_getRootMap(): ' . $e->getMessage());
        }
    }
    
    /**
     * Map fields in API to Magento using rootMap
     *
     * @return array
     * @throws Exception if unable to load or create field map array
     */
    
    protected function _getFieldMap( $element, $data )
    {
        try {
         
            $fieldMap = array();
            
            /**
             * Get root map from cache
             */
            $this->_rootMap = $this->_getRootMap();
            
            /**
             * Build map for selected element
             */
            foreach ( $this->_rootMap[ $element ] as $key => $row ) {
                    
                    $value = @$data[ $row['magento'] ];
                    
                    /**
                     * If value is empty, replace with default value from mapping table
                     */
                    if ( strval( $value ) === '' ) {
                        $value = $row['default'];
                    }
                    
                    /**
                     * Convert value to proper type according to API requirements
                     */
                    switch ( $row['type'] ) {
                        case 'float':
                            $value = floatval( $value );
                            break;
                        case 'int':
                            $value = intval( $value );
                            break;
                         case 'datetime':
                            $value = $this->_formatDate( $value );
                            break;
                         case 'boolean':
                            $value = (boolean) $value;
                            break;
                        default:
                            $value = strval( $value );
                            break;
                    }
                    
                    /**
                     * Separate data into multi-dimensional array 
                     * for use in creating model json objects
                     * 
                     */
                    $fieldMap[ $key ] = array( 'api' => $row['api'], 'magento' => $value );
                
            }
            
            return $fieldMap;
            
        } catch (Exception $e) {
            Mage::throwException('FIELD MAPPING ERROR Jirafe_Analytics_Model_Abstract::_getFieldMap(): ' . $e->getMessage());
        }
    }
    
    /**
     * Return array of mapped Magento fields by element
     *
     * @param string  $element
     * @return array
     * 
     */
    
    protected function _getMagentoFieldsByElement( $element )
    {
        try {
            if ($element) {
                
                $magentoFields = Mage::getModel('jirafe_analytics/map')
                                                ->getCollection()
                                                ->addFieldToSelect('magento')
                                                ->addFilter('element',$element)
                                                ->getData();
                
                return $this->_flattenArray( $magentoFields );
            } else {
                return array();
            }
            
        } catch (Exception $e) {
            Mage::throwException('FIELD MAPPING ERROR Jirafe_Analytics_Model_Abstract::_getFieldMap(): ' . $e->getMessage());
        }
    }
    
    /**
     * Return string of attributes to select from a collection
     *
     * @param string  $element
     * @return string
     *
     */
    
    protected function _getAttributesToSelect( $element = null )
    {
        try {
            if ($element) {
                $compositeElement = explode('|',$element);
                
                if (count($compositeElement) > 1) {
                    $element = $compositeElement[0];
                    $subElement = $compositeElement[1];
                } else {
                    $subElement = null;
                }
                
                $attributes = array();
                
                $fields = $this->_getMagentoFieldsByElement( $element );
                
                foreach( $fields as $field ) {
                    $compositeField = explode('|',$field);
                    
                    if ( !$subElement && count($compositeField) == 1) {
                        $attributes[] = $field;
                    } else if ( $compositeField[0] == $subElement ) {
                        $attributes[] = $compositeField[1];
                    }
                }
                
                return $attributes;
            } else {
                return array();
            }
            
        } catch (Exception $e) {
            Mage::throwException('FIELD MAPPING ERROR Jirafe_Analytics_Model_Abstract::_getFieldMap(): ' . $e->getMessage());
        }
    }
    
    
    /**
     * Flatten arrays structure recursively
     * 
     * @param array   $inArray
     * @return array
     * @throws Exception if unable to flatten array
     */
    
    protected function _flattenArray( $inArray = null )
    {
        try {
            $outArray = array();
           
            if ($inArray) {
                $flatArray = new RecursiveIteratorIterator(new RecursiveArrayIterator($inArray));
                
                foreach($flatArray as $field) {
                    $outArray[] = $field;
                }
            }
            return $outArray;
        } catch (Exception $e) {
            Mage::throwException('UTILITY FUNCTION ERROR Jirafe_Analytics_Model_Abstract::_flattenArray(): ' . $e->getMessage());
        }
    }
    
    /**
     * Extract visit data from Jirafe cookie
     *
     * @return array
     * @throws Exception if unable to access $_COOKIE data
     */
    
    protected function _getVisit()
    {
        try {
            return array(
                'visit_id' => isset($_COOKIE['jirafe_vid']) ? $_COOKIE['jirafe_vid'] : '',
                'visitor_id' => isset($_COOKIE['jirafe_vis']) ? $_COOKIE['jirafe_vis'] : '',
                'pageview_id' => '',
                'last_pageview_id' => ''
            );
        } catch (Exception $e) {
            Mage::throwException('VISIT OBJECT ERROR Jirafe_Analytics_Model_Abstract::_getVisit(): ' . $e->getMessage());
        }
    }
    
    /**
     * Get all Jirafe cookie data
     *
     * @return array
     * @throws Exception if unable to access $_COOKIE data
     */
    
    protected function _getCookies()
    {
        try {
            return array(
                'jirafe_ratr' => isset($_COOKIE['jirafe_ratr']) ? $_COOKIE['jirafe_ratr'] : '',
                'jirafe_lnd' => isset($_COOKIE['jirafe_lnd']) ? $_COOKIE['jirafe_lnd'] : '',
                'jirafe_ref' => isset($_COOKIE['jirafe_ref']) ? $_COOKIE['jirafe_ref'] : '',
                'jirafe_vis' => isset($_COOKIE['jirafe_vis']) ? $_COOKIE['jirafe_vis'] : '',
                'jirafe_reftyp' => isset($_COOKIE['jirafe_reftyp']) ? $_COOKIE['jirafe_reftyp'] : '',
                'jirafe_typ' => isset($_COOKIE['jirafe_typ']) ? $_COOKIE['jirafe_typ'] : '',
                'jirafe_vid' => isset($_COOKIE['jirafe_vid']) ? $_COOKIE['jirafe_vid'] : '' 
            );
        } catch (Exception $e) {
             Mage::throwException('COOKIE OBJECT ERROR Jirafe_Analytics_Model_Abstract::_getCookies(): ' . $e->getMessage());
        }
    }
    /**
     * Format customer data as array
     *
     * @param mixed $data    Mage_Sales_Model_Quote or Mage_Sales_Model_Order
     * @return array
     * @throws Exception if unable to generate customer object
     */
    
    protected function _getCustomer( $data = null, $includeCookies = false )
    {
        try {
            if ( is_numeric($data['customer_id']) ) {
                $customerId = $data['customer_id'];
            } else if ( Mage::getSingleton('customer/session')->isLoggedIn() ){
                $customerId = Mage::getSingleton('customer/session')->getCustomer()->getId();
            } else {
                $customerId = null;
            }
            
            if ( $customerId ) {
                $customer = Mage::getSingleton('customer/customer')->load( $customerId );
                return Mage::getSingleton('jirafe_analytics/customer')->getArray( $customer, $includeCookies );
            } else {
                $customer = Mage::getSingleton('core/session')->getVisitorData();
                $customerId = is_numeric( @$data['visitor_id'] ) ? $data['visitor_id'] : (is_numeric( @$customer['visitor_id'] ) ? $customer['visitor_id'] : 0 );
                if ( isset($data['created_at']) && isset($data['customer_email']) && isset($data['customer_firstname']) && isset($data['customer_lastname']) ) {
                    return array(
                        'id' =>  $customerId,
                        'create_date' => $this->_formatDate( $data['created_at'] ),
                        'change_date' => $this->_formatDate( $data['created_at'] ),
                        'email' => $data['customer_email'],
                        'first_name' => $data['customer_firstname'],
                        'last_name' => $data['customer_lastname']
                    );
                } else {
                    return array(
                        'id' =>  $customerId,
                        'create_date' => $this->_formatDate( $customer['first_visit_at'] ),
                        'change_date' => $this->_formatDate( $customer['last_visit_at'] ),
                        'email' => '',
                        'first_name' => 'GUEST',
                        'last_name' => 'USER'
                    );
                }
            }
        } catch (Exception $e) {
             Mage::throwException('CUSTOMER OBJECT ERROR Jirafe_Analytics_Model_Abstract::_getCustomer(): ' . $e->getMessage());
        }
    }
    
    /**
     * Format store values as catalog array
     * 
     * @param int $storeId
     * @return array
     * @throws Exception if unable to generate catalog object
     */
    
    protected function _getCatalog( $storeId = null )
    {
        try {
            if (is_numeric( $storeId )) {
                return array(
                    'id' => strval($storeId),
                    'name' => Mage::getSingleton('core/store')->load( $storeId )->getName());
            } else {
                return array(
                    'id' => '',
                    'name' => '');
            }
        } catch (Exception $e) {
             Mage::throwException('CATALOG OBJECT ERROR Jirafe_Analytics_Model_Abstract::_getCatalog(): ' . $e->getMessage());
        }
    
    }
    
    /**
     * Format date to Jirafe API requirements: UTC in the ISO 8601:2004 format
     *
     * @param datetime $date
     * @return datetime
     */
    
    protected function _formatDate( $date )
    {
        try {
            return date( DATE_ISO8601, strtotime( $date) );
        } catch (Exception $e) {
            Mage::throwException('UTILITY ERROR Jirafe_Analytics_Model_Abstract::_formatDate(): ' . $e->getMessage());
        }
    }
    
}