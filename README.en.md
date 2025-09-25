# Nextcloud Dify Knowledge Base Integration Plugin

This plugin integrates Nextcloud with the Dify knowledge base, automatically synchronizing files from specified directories in Nextcloud to the Dify knowledge base.

## Features

- Adds a "Knowledge Base" menu item in the admin settings
- Configure Dify API information and directory mapping relationships
- Automatically sync Nextcloud files to the Dify knowledge base
- Supports synchronization of file creation, modification, and deletion operations
- Uses Nextcloud file objects directly for upload, improving efficiency and reliability
- Multi-language support (English and Chinese)

## System Requirements

- Nextcloud 27+ (optimized for Nextcloud 31)
- PHP 7.4+

## Installation

### Method 1: Install from App Store (Recommended)

1. Search for "Dify Knowledge Base Integration" in the Nextcloud admin interface app store
2. Click the install button

### Method 2: Manual Installation

1. Download the plugin folder to the Nextcloud `apps` directory
2. Enable the plugin in the Nextcloud admin interface

## Configuration

1. Log in to the Nextcloud admin account
2. Go to the admin settings page
3. Click the "Knowledge Base" option in the left menu
4. Fill in the configuration interface:
   - Dify URL: The base URL of the Dify service (it is recommended to include the `/v1` path)
     - Correct example: `http://172.16.207.5/v1` or `https://api.dify.ai/v1`
     - Can also be configured as a base address: `http://172.16.207.5` (the plugin will automatically add `/v1`)
   - Dify API Key: The key used to access the Dify API
   - Document Naming Pattern: Select the naming pattern for documents in Dify (four new naming patterns are provided)
     - 📄file 📁directory 📅modifiedDate modifiedTime.md (Recommended): filename+directory+date time format
     - 📁directory 📄file 📅modifiedDate modifiedTime.md: directory+filename+date time format
     - file (directory) modifiedDate modifiedTime.md: filename+directory+date time format
     - (directory) file modifiedDate modifiedTime.md: directory+filename+date time format
   - Performance Optimization Options:
     - Asynchronous Processing: When enabled, file operations will be processed asynchronously in the background without blocking the user interface
     - Batch Processing: When enabled, multiple file operations will be processed together to improve efficiency
     - Processing Delay: Set the delay time for file processing (in seconds) to reduce server load
   - Directory Mapping: Multiple mappings can be configured, each including a Nextcloud directory path and the corresponding Dify knowledge base ID
     - Nextcloud Directory Path: Please enter the path relative to the Nextcloud root directory, e.g.: /test represents the test folder under the root directory
     - Dify Knowledge Base ID: The dataset_id obtained after creating a knowledge base on the Dify platform (note: not kb_id)
       - In the knowledge base list of the Dify admin panel, click "Settings" to view the dataset_id
5. Click the "Save" button to save the configuration

## Usage Instructions

The plugin will automatically monitor the configured directories. When files change, the following operations will be performed:

- **New File**: Upload the file to the corresponding Dify knowledge base
- **Modify File**: First delete the file from the Dify knowledge base, then re-upload the new version
- **Delete File**: Delete the corresponding file from the Dify knowledge base

## Multi-language Support

This plugin supports multiple languages:
- English
- Chinese (Simplified)

The language will automatically switch based on the user's Nextcloud interface language settings.

## License

AGPL-3.0

## Author

Developer Name <developer@example.com>
