# Silverstripe Mailchimp Module

The Silverstripe Mailchimp Module enables you to keep your Mailchimp mailing list in sync with the subscribers and/or members of your Silverstripe website.
The module can be used with subscribers only or can offer a deeper integration with Silverstripe's pre-existing Member object through the use of a DataExtension.
The module syncs data both ways (this can be managed on a field by field basis) so you can rest assured that your data is the same in both systems regardless of wether your users are updating their records via Mailchimp or via your website.


## Version
0.1.1

## Features
**List integration**
* When users subscribe on the website or are manually added through SilverStripe, they will be added into the list in Mailchimp.
* Anyone subscribing through the Mailchimp signup form will be added in the SilverStripe CMS (Mailchimp add a double opt-in process however, you can change the settings using the API).
* Unsubscribes are synced when users unsubscribe from either platform
* If you have additional information that has been created in Mailchimp as merge field’s e.g. address or subscription expiry date, when these are edited in SilverStripe of Mailchimp it will be updated in both
* SilverStripe will create/delete a new list as and when Mailchimp has a new/removed list
* If a new subscriber is created in your mailing list but that person is already a ‘member’, SilverStripe will recognise the subscriber has a related member object and update all member fields in Mailchimp
* When a subscriber becomes a member and completes the member transaction, a member object is created in SilverStripe, and their details will be updated in Mailchimp; from subscriber to member
* When a member decides to unsubscribe to email marketing, but remains a member, they are unsubscribed in SilverStripe and in Mailchimp
* When a member re-subscribes to email marketing on the website, they are added back into the list in SilverStripe and in Mailchimp
* If a subscriber unsubscribes and tries to re-subscribe through the website, they will receive an error message. They will only be able to re-subscribe through Mailchimp’s sign up form with double opt-in confirmation email or admin can add the subscriber manually

**Segmentation**
* When an event page is created in SilverStripe, a static segment is created in Mailchimp
* If you have more than one list, you can choose, through SilverStripe, which list or multiple lists you want the static segment to be created against in Mailchimp
* When a user on the website clicks ‘attend’ on the website, they are added into the segment in Mailchimp & SilverStripe
* You can manually add people to an event in SilverStripe and they will also be added in the Mailchimp static segment
* When someone decides to un-attend, they will be removed from the static segment in both SilverStripe and Mailchimp

## Requirements
```
silverstripe/cms: 3.1.x
```

## Installation
* Download the code base by either cloning this repository or downloading the provided .zip file
* You can name the module directory whatever you like, we reccomend silverstripe-mailchimp-module
* Place the module directory in the sites web root (i.e. at the same level as /mysite)
* Run a /dev/build and /?flush=all against the website
* Log in to your Mailchimp account and generate a new API key (see [Mailchimp's documentation](http://kb.mailchimp.com/accounts/management/about-api-keys))
* Log in to your websites CMS as an administrator and go to Settings > Mailchimp, here you can enter your API key and save
* You can now 'Update Lists from Mailchimp' which will create a new MCList instance for each of your existing Mailchimp lists
* For each list, click edit and go to the 'Field Mapping' tab, this allows you to map each merge field of your Mailchimp list to the appropriate DataObject i.e. Member | Subscriber) and Property (i.e. Last Name) and specify which direction data should be syncronised
* Go back to Mailchimp and set up a web hook (see [Mailchimp's documentation](http://kb.mailchimp.com/integrations/other-integrations/how-to-set-up-webhooks)) which points to /MCSync (i.e. https://www.example.com/MCSync), ensure that the 'via the API' option **is not ticked** when setting up this web hook
* You can verify that the module is installed correctly by adding a new Subscriber or Member in your websites CMS and/or creating or updating a test record in your Mailchimp list

## Troubleshooting
The module will create a directory to hold logs in your websites /assets folder at '/assets/silverstripe-mailchimp-module/logs/' this will contain an info.log and error.log file (if notices and/or errors have been rasied) which should provide straight forward explanations of what has gone wrong.

## Licence
This work is licensed under the Creative Commons Attribution-NonCommercial 4.0 International License.
To view a copy of this license, visit [http://creativecommons.org/licenses/by-nc/4.0/](http://creativecommons.org/licenses/by-nc/4.0/).

## Contact
This module is built by [Quadra Digital](https://www.quadradigital.co.uk) and has been made open source for free, we are unlikly to be able to offer much support however if you have any queries regarding usage, licencing, bugs or improvements please use one of the appropriate contact below.
#### Technical
Joe Harvey <[joe.harvey@quadradigital.co.uk](mailto:joe.harvey@quadradigital.co.uk)>
#### Administrative
Ping Ho <[ping.ho@quadradigital.co.uk](mailto:ping.ho@quadradigital.co.uk)>

### To Do
* Rather Attempting to Add Subscriber to List Only On Subscriber Creation, Try Adding Whenever The Subscriber Has No MC Member ID (Incase A Sync Fails On Creation)
* Amend MCSync Script to Be On a Single List Basis, for Use With MailChimp Webhooks
* Allow For List Creation On Website (And Sync To MailChimp)
* Allow For Static Segment Creation On Site (And Sync To MailChimp)
* Ability To Add Subscription Records To Static Segment (And Sync To MailChimp)
* Force Write On Subscription Record When Linked Or Unlinked To A Member Object

### Known Issues
* Deleting a Record On MailChimp (As An Admin) Does Not Mark The Record As Unsubscribed, You Must Unsubscribe Record, Sync To Site, Then Later Delete
* If A MailChimp Record Is Unsubscribed (via MailChimp Side) And That Subscription Is Added To A Static Segment (i.e. Before The Website Has Been Brought Back In Sync With MailChimp) The API Call Will Succeed But If Calling Action Via AJAX The AJAX Call Itself Will Return An Error (i.e Won't Get In To You success() Function) Due To Warning On Line 2886 of MCAPI.class.php