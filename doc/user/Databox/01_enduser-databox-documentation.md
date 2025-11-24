---
title: Databox Client descripion
status: pending review
---

# Databox User Documentation

---

# 1.Databox

---

## 

## **The Interface**

The media management interface is called **"Databox"**. It is divided into several areas:

### **The Sidebar**

On the left side of the Databox window is a sidebar.

It is composed of 3 tabs:

* **The collection tree:** to navigate among the available Workspaces and Collections.  
* **The facets:** to filter results according to different criteria and guide your search.  
* **The baskets:** a selection of assets. Baskets are created to group assets, temporarily or not, for an action: editing, exporting, sharing, publishing a gallery on an external site, etc.

### **The Search Bar**

The top of the window features a search bar.

* **Simple search:** Enter one or more terms to find assets.  
* **Advanced search:** Enter one or more conditions to perform searches in specific fields.  
* **Sorting:** To classify the results according to your needs, and/or by major sections for an even clearer display.

### **The Results Display Area**

The central area allows you to view all assets resulting from a search. By default, they are presented as thumbnails (two other display modes are available in the display preferences: list and masonry).

### **The Actions Menu**

The main actions menu is located just above the results display area. It allows you to perform numerous actions on assets:

* Add assets to a basket  
* Export  
* Move  
* Edit attributes  
* Duplicate  
* Share  
* Delete

To the right of the actions menu, on the same line, are the **User Preferences Display**. They group the following options:

* Display mode for results  
* Display preferences for asset views: whether or not to show titles, collections, tags, hover preview  
* Choice of thumbnail size  
* Choice of global display size and ratio between preview and attributes  
* Creation and management of personal attribute lists

### **Adding Assets**

The "+" icon in the bottom right corner of the window allows you to add assets to a collection by Browse the Workspace tree.

Other import methods are available in Phrasea. For example, it is also possible to import files from the sidebar by hovering over the title of the destination collection.

---

## **Workspaces and Collections**

We will describe other terms as we go in this documentation, but to start importing assets, we need to understand what **Workspaces** and **Collections** are in Phrasea.

We also invite you to visit the "Phrasea Vocabulary" page, which brings together all these concepts.

### **Definitions**

**Workspace**

A Workspace is a set that contains collections. It allows you to configure languages, attributes, renditions, tags, and permissions.

**Collection**

A collection groups assets together. Collections can be hierarchical. They have a set of parameters (permissions and tag rules).

### **Organizing Your Assets**

The "**Workspaces**" tab represents the content tree to which you have rights. In Phrasea, a **Workspace** is a space that contains collections.

Within each Workspace, there are one or more collections, which are organized hierarchically. 

Each **Collection** groups assets: photos, videos, sounds, PDF documents, and more.

### **Creating New Collections**

#### Creating a First Collection in the Workspace

* Click on the "**folder**" icon.  
* Select "**Add the collection to this Workspace**".

  #### Creating a Sub-collection

Collections are hierarchical. To create a new collection within an existing collection, you can do so directly in the content tree, or on the fly when importing assets.

* Click on the "**…**" next to the collection name.  
* Select "**Create a new collection in this one**". A window will open.  
* Name it, then click "**Save**".  
* The collection will appear in your content tree.  
* Need to translate the collection name into multiple languages? Click on the flag next to the collection name.

Tip: If you prefer to import assets and create a sub-collection on the fly, simply open the import window, browse the destination collection's content tree, and add a new collection in the chosen location.

---

### 

## **Importing**

### **Adding Assets**

Adding assets (photos, videos, audio documents, PDFs, etc.) is very easy in the DAM. There are several methods to import assets into Phrasea:

#### Add Assets from the \+ icon

* Click on the "+" button located in the bottom right of the interface. A window opens.  
* Select files or drag and drop them into the window.  
* Choose a Workspace if there are multiple, as well as the destination collection.  
* Select the documents' confidentiality (Public, Private, or Secret).  
* Validate the transfer by clicking the **"Add"** button.

  #### Add Assets Directly from a Collection

* In the sidebar:  
* Browse the tree and choose your destination collection for these new assets.  
* When hovering over the collection title, the "..." icon appears. Click on it.  
* Select **"Add a new asset to the collection"**.  
* The rest of the process is the same as the one previously mentioned.

  #### Drag and Drop Documents into Databox

A simple drag and drop anywhere on the window opens the import window. The documents are in place for adding; all that's left is to select a destination collection and follow the same procedure as before.

#### Using Phrasea Uploader

Besides importing within Databox, it is possible to use **Phrasea Uploader**.

Phrasea Uploader is a dedicated application for importing files.

* Import from any device (phone, tablet, desktop)  
* Large file volumes  
* Construction of pre-indexing forms  
* Customizable input channels  
* Automated imports (retrieving media from other applications)

Please contact us for more information on Phrasea Uploader.

### **Adding with Pre-indexing**

Pre-index your content during import. Instead of directly clicking the **"Import"** button after loading your assets, fill out the form presented to you. A form allows you to fill in information in the different attributes that have been configured: title, description, keywords, credit, tags, etc. From this same window, it is possible to perform pre-indexing in several languages simultaneously.

### **Using Templates on Import**

Pre-indexing templates allow you to automatically pre-fill metadata when importing assets. They speed up users' work while ensuring document consistency. Less input also means fewer errors or risks of omissions.

Users can create templates adapted for different uses, which can be shared if needed. 

#### Creation of a template

* Fill in the attributes, tags, etc., on assets selected for import, then check the box in the import form: **"Save values as a template for reuse"**.  
* Give the template a name.  
* Fill in the options available within a template:  
  * Replace or not a template that has been applied  
  * Apply or not to the collection  
  * Include or not sub-collections  
  * Include or not attributes  
  * Include or not confidentiality  
  * Include or not tags  
  * Make the template Public or only available for use by the user who created it.  
* Click the **"Add"** button to transfer the assets.

  #### Using a template

For the next import, the templates you have created will appear in the drop-down list of the **"Fill with template"** field at the top of the form.

Select one or more templates, as they can be combined.

## ---

## 

## **Search**

### **Search: Introduction**

[The search engine used by Phrasea is Elastic.](https://www.elastic.co/)

Elasticsearch is a distributed, open-source solution designed for search and analytics engines, optimized for speed, scalability, and AI applications. As a retrieval platform, it stores structured, unstructured, and vector data in real-time—offering fast hybrid and vector search, powering observability and security, and enabling AI-driven applications to achieve high performance, optimal accuracy, and relevance.

Among other things, Elasticsearch allows you to:

* Search for documents in free text, by exact words, or precise phrases.  
* Search in multiple fields at once.  
* Combine numerous criteria (conditions).  
* Perform fuzzy searches.  
* Search with value ranges (dates, numbers).  
* Finally, adjust the relevance of results using custom scoring functions.  
* These search methods with Elastic are numerous and detailed here on the Elastic website: [https://www.elastic.co/docs/explore-analyze/query-filter](https://www.elastic.co/docs/explore-analyze/query-filter)

### **Simple Search**

In Databox, the search bar is located above the results display. Place the cursor in the search bar and enter a term.

The **"Search Suggestions"** feature helps guide the user in their search by proposing terms in the search bar. This also helps you avoid missing important information.

By default, the results are displayed as a grid (thumbnails).

In Phrasea, there is an **automatic highlighting feature for terms** in metadata. When a user performs a search, the corresponding terms are visually highlighted, which facilitates the quick identification of relevant information. This function is active and applies to all indexed attributes. In the specific case of multi-valued attributes, the strict term is considered.

### **Faceted Search**

Faceted search can be seen as a map of the search results. After launching a search, facets create a kind of plan of the results by distributing them according to different criteria such as date, category, location, or document type. Each facet represents a potential path to explore to refine and orient the search more precisely.

#### Navigating via facets

* Click on the **"Facets"** tab in the sidebar.  
* For each facet, check the values you are interested in to progressively refine the search. Example: choice of a date range, keywords, tags, country, city, credit, etc.

  #### Combining or Excluding Facets

The combination or exclusion of facets allows you to broaden or narrow the scope of the search. This ensures you find the exact information more effectively.

After selecting a value for a facet (for example: the tag "New"), click on the "..." menu for the value. Several options are offered:

* **Disable:** to exclude this value from the search. All assets with tags except the "New" tag will be displayed.  
* **Enable:** when you have excluded a value from the search. The facet will then be reactivated.  
* **Edit:** to modify a condition (see "Advanced Search" below).  
* **Remove:** to remove this facet from your search.

  #### Configuring facets to display

You can choose which facets are displayed in the sidebar. To configure the facets, you must have Workspace management rights.

Go to the Workspace editor, click on the **"Attributes" tab**, and for each attribute, decide whether or not to check the "Facets" box.

### **Advanced Search**

Advanced search allows you to quickly find assets by combining several criteria and, in particular, performing searches on specific attributes. Click on the funnel-shaped icon to open advanced search.

**Adding Conditions**

Click on **"Add condition"**.

It is from this window that conditions must be added:

* Selection of a field to search in:  
  * Either a so-called technical field (collection, dates, file size or type, tags, etc.)  
  * Or among your attributes (credit field, title, caption, country, city, keywords, etc.)  
* Selection of the operator: After choosing the field, choose the operator ("starts with", "exists", "contains", "is equal to", etc.; these operators change depending on the chosen condition).  
* Selection of the value: Finally, enter a value: a term, a date, etc.

You can then launch the search by clicking the **"Add"** button, or add one or more other conditions, as well as one or more groups of conditions to combine search filters on specific fields.

### **Sorting**

You can sort and group your search results: Sort by creation date, modification date, by tags, or attributes. By grouping the results into major sections, you get a display adapted to your needs.

Example: A sort on the "Country" attribute placed in the 1st position, then the city is added. Uncheck the unwanted attributes and move these two elements to the top by drag and drop.

The results are displayed first by country, then by cities.

Check "Group by sections" to add group separators to the results presentation. Click the "Apply" button. The results are displayed first by country, then by cities, separated by major sections.

---

## **Display**

Users can customize the display of their results.

Three views are available in Phrasea: thumbnails, list, and masonry. To change these views, click on the corresponding icon next to the results count display.

### **Display Preferences**

The Databox interface adjusts to your needs. The display only shows what you want to see. To do this, go to **Display Preferences**. You can:

* Choose the size of your thumbnails  
* Choose whether or not to display the title, tags, collections (as well as the number), and the image preview on hover  
* Choose preview preferences: autoplay for videos, choose to display only the image, only the attributes, or both  
* Finally, choose the overall size of the preview, as well as the size dedicated to displaying attributes.

### **Attribute Lists**

You can create lists to pre-select the attributes you want to see displayed when you hover over your assets in the results display area.

Example: If you wish to display only geographical information, create a list with attributes such as: City, Country, GPS Position… (you can choose to add a map display instead of coordinates).

### **Themes**

Configure themes for your Databox interface. You can change the theme (which includes the colors of your interface, the shape of the buttons, etc.).

### **Preview**

Double-click on a thumbnail to view a document and start working.

* By expanding the "**Asset attributes**" tab, you can access the information entered in the record, with the corresponding attributes.  
* Below, the "**Information**" tab provides information on the asset's owner, its ID, and various technical fields, such as the date the asset was added to the DAM, the date of the last modification, and the collection and workspace where it is located.  
* The "**Discussion**" tab allows users to send each other messages about the image and also groups annotations.  
* The following tabs are the **integrations**: Several integrations can be installed and configured in your DAM (Auto-indexing, Remove Background, simple retouching, etc.).

---

## **Edit**

### **Batch Editing Assets**

This section explains how to modify multiple assets simultaneously.

#### Start Batch Editing

* Select the assets you want to modify.  
* Click the **"Edit"** button on the menu bar.  
  An indexing window opens and displays all the selected assets.

  #### Recommended Methodology

In the indexing window, you can organize your assets. Select the assets to index, from the most generic to the most specific. Adjust the size of the preview thumbnails in the **Display preferences** to make it easier to view the assets.

#### Entering Information

The entry area allows you to preview documents and display the fields and tags to be modified.

* Enter information into the fields. It's possible to index in multiple languages simultaneously.  
* Values are visible on the right side of the window.  
* To undo or redo an action, use the "**Undo**" or "**Redo**" buttons.  
* Before saving, compare the new values with the original values.  
* Click "**Save**" to validate the changes.

### **Editing One Asset at a Time**

If you only want to modify a single asset, follow these steps:

* Select the asset you want to edit.  
* Click the "Edit" button.  
  The "Attributes" tab opens and presents the fields to be filled in.  
* Enter the information in the various fields. Tags, however, must be entered from the "**Edit**" tab.  
* Click on "**Save**".

---

## **Performing Actions on Assets**

### **Exporting Assets**

To download documents:

* Select the items from the results grid.  
* Click the **"Export" button** on the actions bar.  
* The window that opens allows the user to choose the renditions (sub-definitions of the assets, configurable in the DAM) to export.

### **Deleting Assets**

To permanently delete one or more assets, select the items, then click the **"Delete" button**.

A window will ask for confirmation to permanently delete from the DAM.

### **Moving Assets**

To move assets from one collection to another:

* Click the arrow next to the "**Edit**" button.  
* Select **"Move"**.  
  A window opens.  
* Expand the workspaces and collections you have access to in order to choose the collection where you want to move the item.  
* Click "**Move**" to finalize the move.

### **Duplicating**

Two types of copies can be made in Phrasea:

* **Asset duplication**  
* **Copy (shortcut)**

**Asset duplication** corresponds to the creation of a physical copy of a file. This means that a new file is generated, with its own unique reference and metadata.

**Copy**, also called a "reference," does not create a new physical file. It creates a new entry in the system that points to the original file. It's the equivalent of a shortcut. The original asset remains unique, but it is accessible from multiple locations or names.

1. Select the case that corresponds to your needs.  
2. Choose a destination collection.  
3. Finally, click "**Copy**."

### **Managing Asset Versions**

To manage and access the different versions of an asset, select it in the results display window and click **"Edit"**.

Go to the **"Versions" tab** to access all versions. 

To create a new version of an asset:

* Click the gear icon on each thumbnail and choose "**Replace source file**." Substitute the file. The new version of this asset will appear in the successive versions.  
* Double-click on an asset. The detailed view opens. Make modifications to the asset, for example, with the retouching tool. Once the modifications are made, save the asset.

An option will ask you to save as:

* **New asset**  
* **Rendition**  
* **Replace the asset's source**

If you click "**Replace the asset's source**," your asset will be automatically updated on the results grid. Go to the "**Versions**" tab; the current version will be the one you have defined.

Thanks to this display, you can easily compare the different versions to identify changes. The detailed information on the assets allows you to see who modified them and on what date, thus improving traceability.

Restore an earlier version if necessary, by making it the active version:

You have a choice to delete the old version, download it, or restore it by clicking **"Replace the asset's source"**.

### **Sharing**

* **Public mode sharing**  
* **Advanced sharing**

By clicking the "**Share**" button, you can easily share your media in Phrasea.

After selecting an asset:

* Choose between the "**Standard**" mode for a permanent sharing link, in Public mode, or  
* The "**Advanced**" mode for time-limited sharing.

The sharing bar also facilitates social media integration.

### **Annotating**

Phrasea offers a visual annotation feature that allows you to comment directly on a specific area of an asset. This collaborative function lets you click on a part of the asset to place an annotation there, thus facilitating detailed feedback, corrections, or targeted validations (e.g., flagging a correction to be made during retouching, designating a location, or discussing a specific topic with users).

Each annotation can be accompanied by a message and a mention to notify one or more collaborators.

To annotate an asset, double-click on a document. The detailed view opens.

At the bottom of the image, a menu is present. The annotation tool is circled above. It's possible to hide this menu bar by clicking the "x." It will then be indicated by an arrow on the left side of your screen if you wish to reopen it later.

When you open the menu, the annotation function presents various tools:

* **Text tool**: to add text to the image  
* **Target, rectangle, and circle tools**: to draw these shapes  
* **Arrow and line tools**: to draw these lines  
* **Brush**: to choose the color of the shapes and lines  
* **Size**  
* **Below**: zoom in, zoom out, center the image, see the whole image.

You can also resize the shape by placing the cursor on one of the circles.

* Color choice for the circle / rectangle / line, etc.  
* Select the element you have traced, then choose the color, size, etc.  
* Rename the element if necessary by clicking on the title or delete it by clicking on the trash can.

For a user to see an annotation, they must click on the circled element below. When there are several elements, they must repeat the operation.

### **Discussing**

Collaborative messaging:

The Phrasea DAM includes a messaging system that allows users to exchange directly on visuals. For each asset, a comment area is available, accessible from the detailed view. This feature allows you to leave messages, ask questions, or give targeted feedback on an image. Users can mention one or more users via @ (followed by the user's name), which generates a notification to facilitate collaboration. Exchanges remain visible to all users with access to the asset. This allows for a clear tracking of decisions and validations. This messaging is ideal for streamlining validation or retouching processes and centralizing discussions related to a file.

NB: A user must have rights that allow them to enter messages and notify users from the "Discuss” function.

### **Getting Notified**

The system includes notifications to keep users informed in real-time about actions performed on assets. A user can be notified if they:

* **Are individually subscribed to an asset**: They can then choose to:  
  * Be notified each time the asset has been updated  
  * When the asset is deleted  
  * When there is a new comment on the asset  
    They can unsubscribe from an asset's tracking.  
* **Are subscribed to a collection**.

The user has the option to configure their notification preferences: whether to receive them by email, in the application, or both.

NB: All actions can be triggered individually from each asset in the results grid. To do this, click on the gear icon on each thumbnail displaying an asset.

---

## **Baskets**

In Phrasea, you can create baskets to temporarily or permanently save certain assets.

### **Accessing a Basket**

You can find all baskets in the side panel, under the **"Baskets" tab**.

Click on a basket's title to view its contents.

### **Creating Baskets**

To create a basket, simply click the "**Create new basket**" button in the side panel. Give it a title, and an optional description, then click "**Save**."

### **Adding Assets to a Basket**

Above the results display area, the button with the basket's title appears. Select the basket you want to add assets to. To do this, use the arrow next to the default basket's title (or the last basket used) to select the desired basket.

* **Adding a single asset to a basket**

In the results:

Select an asset to add to the basket, then click the basket icon on the thumbnail. The number of assets in the basket will increase as you add documents.

* **Selecting a batch of assets to add to a basket**

Select multiple assets, then click the icon on the button.

### **Performing Actions on Assets in a Basket**

Click on the basket as mentioned previously to view its contents, then select one or more assets. Various action buttons are available, depending on your permissions: export, index, move, share, delete, or remove the asset from the basket.

The action buttons are grayed out. Select assets to activate them. If they remain grayed out, it means you do not have sufficient permissions.

Just like in the results display area, you can choose your display preferences (adjust thumbnail size, attributes, choose whether to display titles, tags, collections, hover preview, switch to thumbnail or list view, etc.).

### **Sharing Baskets**

You can share baskets with other users of the DAM.

To do this, right-click on the basket's title, then click **"Edit Basket”**.

A window will open.

Go to the **"Permissions" tab** and choose the group(s) and/or user(s) in the system to share it with. Finally, select the appropriate permissions to grant them.

### **Publishing Baskets**

You can publish your baskets as web galleries on an external site.

Phrasea Expose is integrated directly, allowing you to view your publications and customize the layout and colors to match your brand's graphic charter.

Many options are available for each publication, such as adding terms and conditions, a logo, and protecting a publication with a password.

Click on **“Integrations”**.

To continue, you must have access to Expose.

Contact us to get Expose or for more information.

---

