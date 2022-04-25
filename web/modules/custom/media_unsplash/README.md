# CONTENTS OF THIS FILE

* Introduction
* Requirements
* Installation
* Configuration
* Maintainers

## INTRODUCTION

The Media: Unsplash module adds Unsplash as a separate media provider and saves
Unsplash images to your media.

All photos published on Unsplash are licensed under Creative Commons Zero which
means you can copy, modify, distribute and use the photos for free, including
commercial purposes, without asking permission from or providing attribution to
the photographer or Unsplash.

For more information on Unsplash please visit this page:
<https://unsplash.com/help>

## REQUIREMENTS

This module requires the following modules:

* Entity Browser (<https://www.drupal.org/project/entity_browser>)

## INSTALLATION

 Media: Unsplash can be installed via the standard Drupal installation process.

 Once installed, you must create application on Unsplash API to get access key
 and enter the application name and access Key into the
 Media: Unsplash settings page.

 Unsplash API
 You need first register on Unsplash <https://unsplash.com/developers>
 Create application <https://unsplash.com/oauth/applications/new>
 Enter title and description, leave "Redirect URI" empty.
 Permission as they are by default.

 After application is created copy Application ID inside Drupal module settings
 page.

 By default you can make up to 50 requests per hour while application is
 in development mode. You can increase this to 5000 per hour, but you need to
 send screenshot of your app. You can also mention that is Drupal module in
 question.

## CONFIGURATION

    1. Register at Unsplash.com and obtain a Unsplash API key:
       <https://unsplash.com/oauth/applications/new>.

    2. Navigate to Administration > Configuration > Media > Unsplash Media
       (`admin/config/media/unsplash`) and add your
       Unsplash API application name and key.

    3. Navigate to Administration > Configuration > Content Authoring >
       Entity Browser (`/admin/config/content/entity_browser`) and click
       'Add Entity browser'.

    4. Complete the required fields and click Save.

    5. Navigate to Administration > Configuration > Content Authoring >
       Entity Browser > Widget Settings
       (`/admin/config/content/entity_browser/{entity_browser_machine_name}
       /widgets`) and select 'Unsplash' from the 'Add widget plugin' dropdown.
       Configure the operations.

    6. Click Save to save the Entity Browser Unsplash plugin.

## MAINTAINERS

Supporting organizations:

* Float - <https://www.drupal.org/float>
