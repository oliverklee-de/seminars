Roles related to event records
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

**Organizers** can be listed on the event list view and the single
view. Each event must have at least one organizer. The organizers
receive an email when someone signs up for an event (or when someone
unregisters). The first organizer is used as the sender for the
emails to the attendees. So each organizer must have a valid email
address.

**Attendees** are the front-end users who are signed up for an event.
They van view the events for which they are registered in the “my
events” view. The attendees’ contact data is stored in the front-end
user record, not in the registration records. The extension may be
configured so that attendees can my the list of registrations for
their events in the front end.

**Speakers** are the persons speaking at an event. There can be
several speakers for an event (and even none). The speakers are listed
on the event list view and the event single view. The speakers
currently are not used for any email related activities; so they do
not need to have an email address. If speakers should be able to view
the registration lists for their events, their corresponding front-end
user needs to be set as event manager for their events.

**Tutors, partners, tutors and leaders** are just speakers that are
listed under a different heading.

**Owners** are the front-end users who have created an event record in
the front end and who can view the registration lists.
