## Documentation

### Installation

#### Composer
```bash
composer require quadra-digital/silverstripe-mailchimp-module
```
#### Manual
* Download the code base by either cloning this repository or downloading the provided .zip file
* Download the dependencies listed in requirements
* You can name the module directory whatever you like, we reccomend mailchimp-module
* Place the module directory in the sites web root (i.e. at the same level as /mysite)

## Configuration
* Run a /dev/build and /?flush=all against the website
* Log in to your Mailchimp account and generate a new API key (see [Mailchimp's documentation](http://kb.mailchimp.com/accounts/management/about-api-keys))
* Log in to your websites CMS as an administrator and go to Settings > Mailchimp, here you can enter your API key and save
* You can now 'Update Lists from Mailchimp' which will create a new MCList instance for each of your existing Mailchimp lists
* For each list, click edit and go to the 'Field Mapping' tab, this allows you to map each merge field of your Mailchimp list to the appropriate DataObject i.e. Member | Subscriber) and Property (i.e. Last Name) and specify which direction data should be syncronised
* Go back to Mailchimp and set up a web hook (see [Mailchimp's documentation](http://kb.mailchimp.com/integrations/other-integrations/how-to-set-up-webhooks)) which points to /mailchimp (i.e. https://www.example.com/mailchimp), ensure that the 'via the API' option **is not ticked** when setting up this web hook
* In order to get in sync for the first time you may need to either: 1) run /mailchimp/MCSync if you already have populated Mailchimp list(s) and have no kind of subscriber records stored on your website. 2) Write a script to create a new MCSubscription object and relate it to each of your Member objects.
* You can verify that the module is installed correctly by adding a new Subscriber or Member in your websites CMS and/or creating or updating a test record in your Mailchimp list