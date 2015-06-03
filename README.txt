@TODO
- Rather Attempting to Add Subscriber to List Only On Subscriber Creation, Try Adding Whenever The Subscriber Has No MC Member ID (Incase A Sync Fails On Creation)
- Amend MCSync Script to Be On a Single List Basis, for Use With MailChimp Webhooks 
- Allow For List Creation On Website (And Sync To MailChimp)
- Allow For Static Segment Creation On Site (And Sync To MailChimp)
- Ability To Add Subscription Records To Static Segment (And Sync To MailChimp)
- Force Write On Subscription Record When Linked Or Unlinked To A Member Object

@BUGS
- Deleting a Record On MailChimp (As An Admin) Does Not Mark The Record As Unsubscribed, You Must Unsubscribe Record, Sync To Site, Then Later Delete
- If A MailChimp Record Is Unsubscribed (via MailChimp Side) And That Subscription Is Added To A Static Segment (i.e. Before The Website Has Been Brought Back In Sync With MailChimp) The API Call Will Succeed But If Calling Action Via AJAX The AJAX Call Itself Will Return An Error (i.e Won't Get In To You success() Function) Due To Warning On Line 2886 of MCAPI.class.php  