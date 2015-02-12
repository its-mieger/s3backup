<?php

	namespace S3Backup;

	class S3Object {

		protected $key;
		protected $body = null;
		protected $contentType = null;
		protected $metaData = array();
		protected $acl = array(
			'Owner' => array(),
			'Grants' => array()
		);

		/**
		 * Creates a new instance
		 * @param string $key The object key
		 */
		public function __construct($key) {
			$this->key = $key;
		}

		/**
		 * Gets the object body
		 * @return string|null The body data
		 */
		public function getBody() {
			return $this->body;
		}

		/**
		 * Sets the object body
		 * @param string|null $body The object body data
		 */
		public function setBody($body) {
			$this->body = $body;
		}

		/**
		 * Gets the object key
		 * @return string The object key
		 */
		public function getKey() {
			return $this->key;
		}


		/**
		 * Gets the meta data array for the object
		 * @return array
		 */
		public function getMetaData() {
			return $this->metaData;
		}

		/**
		 * Sets the meta data array for the object
		 * @param array $metaData The meta data as array
		 */
		public function setMetaData(array $metaData) {
			$this->metaData = $metaData;
		}

		/**
		 * Sets the object owner
		 * @param array $owner The owner data
		 */
		public function setOwner(array $owner) {
			$this->acl['Owner'] = $owner;
		}

		/**
		 * Gets the object owner data
		 * @return array The owner data
		 */
		public function getOwner() {
			return $this->acl['Owner'];
		}

		/**
		 * Sets the object grants
		 * @param array $grants The object grants
		 */
		public function setGrants(array $grants) {
			$this->acl['Grants'] = $grants;
		}

		/**
		 * Gets the object grants
		 * @return array The object grants
		 */
		public function getGrants() {
			return $this->acl['Grants'];
		}

		/**
		 * Gets the object content type
		 * @return string The content type
		 */
		public function getContentType() {
			return $this->contentType;
		}

		/**
		 * Sets the object content type
		 * @param string $contentType The object content type
		 */
		public function setContentType($contentType) {
			$this->contentType = $contentType;
		}

		/**
		 * Packs all additional data (all data except body) for this object on array
		 * @return array The packed data as array
		 */
		public function packAdditionalData() {
			return array(
				'key' => $this->key,
				'contentType' => $this->contentType,
				'metaData' => $this->metaData,
			    'acl' => $this->acl
			);
		}

		/**
		 * Sets the object properties using packed data
		 * @param array $packedData The data array as returned by {@link packAdditionalData()}
		 */
		public function unpackAdditionalData(array $packedData) {
			$this->key = $packedData['key'];
			$this->contentType = $packedData['contentType'];
			$this->metaData = $packedData['metaData'];
			$this->acl = $packedData['acl'];
		}


	}