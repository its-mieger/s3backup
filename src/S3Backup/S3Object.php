<?php

	namespace S3Backup;

	class S3Object {

		protected $key;
		protected $stream = null;
		protected $contentType = null;
		protected $cacheControl = null;
		protected $expires = null;
		protected $contentEncoding = null;
		protected $contentDisposition = null;
		protected $contentLanguage = null;
		protected $websiteRedirectLocation = null;
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
		 * Gets the body stream handle
		 * @return resource|null The body stream handle
		 */
		public function getStream() {
			return $this->stream;
		}

		/**
		 * Sets the body stream handle
		 * @param resource|null $handle The body stream handle
		 */
		public function setStream($handle) {
			$this->stream = $handle;
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
		 * Gets the object cache control data
		 * @return string|null The cache control data
		 */
		public function getCacheControl() {
			return $this->cacheControl;
		}

		/**
		 * Sets the object cache control data
		 * @param string|null $cacheControl The cache control data
		 */
		public function setCacheControl($cacheControl) {
			$this->cacheControl = $cacheControl;
		}

		/**
		 * Gets the object content disposition
		 * @return string|null The object content disposition
		 */
		public function getContentDisposition() {
			return $this->contentDisposition;
		}

		/**
		 * Sets the object content disposition
		 * @param string|null $contentDisposition The object content disposition
		 */
		public function setContentDisposition($contentDisposition) {
			$this->contentDisposition = $contentDisposition;
		}

		/**
		 * Gets the object content encoding
		 * @return null|string The content encoding
		 */
		public function getContentEncoding() {
			return $this->contentEncoding;
		}

		/**
		 * Sets the object content encoding
		 * @param null|string $contentEncoding The content encoding
		 */
		public function setContentEncoding($contentEncoding) {
			$this->contentEncoding = $contentEncoding;
		}

		/**
		 * Gets the object content language
		 * @return null|string The content language
		 */
		public function getContentLanguage() {
			return $this->contentLanguage;
		}

		/**
		 * Sets the object content language
		 * @param null|string $contentLanguage The content language
		 */
		public function setContentLanguage($contentLanguage) {
			$this->contentLanguage = $contentLanguage;
		}

		/**
		 * Gets the object expires header
		 * @return null|string The expires header
		 */
		public function getExpires() {
			return $this->expires;
		}

		/**
		 * Sets the object expires header
		 * @param null|string $expires The expires header
		 */
		public function setExpires($expires) {
			$this->expires = $expires;
		}

		/**
		 * Gets the object website redirect location
		 * @return null|string The object website redirect location
		 */
		public function getWebsiteRedirectLocation() {
			return $this->websiteRedirectLocation;
		}

		/**
		 * Sets the object website redirect location
		 * @param null|string $websiteRedirectLocation The object website redirect location
		 */
		public function setWebsiteRedirectLocation($websiteRedirectLocation) {
			$this->websiteRedirectLocation = $websiteRedirectLocation;
		}


		/**
		 * Packs all additional data (all data except body) for this object on array
		 * @return array The packed data as array
		 */
		public function packAdditionalData() {
			return array(
				'key' => $this->key,
				'contentType' => $this->contentType,
				'contentEncoding' => $this->contentEncoding,
				'contentLanguage' => $this->contentLanguage,
				'contentDisposition' => $this->contentDisposition,
				'cacheControl' => $this->cacheControl,
				'expires' => $this->expires,
				'websiteRedirectLocation' => $this->websiteRedirectLocation,
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
			$this->contentEncoding = $packedData['contentEncoding'];
			$this->contentLanguage = $packedData['contentLanguage'];
			$this->contentDisposition = $packedData['contentDisposition'];
			$this->cacheControl = $packedData['cacheControl'];
			$this->expires = $packedData['expires'];
			$this->websiteRedirectLocation = $packedData['websiteRedirectLocation'];
		}


	}