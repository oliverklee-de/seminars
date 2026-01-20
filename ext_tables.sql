#
# Table structure for table 'fe_users'
#
CREATE TABLE fe_users
(
	tx_seminars_registration int(11) unsigned DEFAULT 0 NOT NULL,
	default_organizer        int(11) unsigned DEFAULT 0 NOT NULL,
	available_topics         text
);


#
# Table structure for table 'tx_seminars_test'
#
CREATE TABLE tx_seminars_test
(
	title tinytext
);


#
# Table structure for table 'tx_seminars_test_mm'
#
CREATE TABLE tx_seminars_test_test_mm
(
	uid_local   int(11) unsigned DEFAULT 0 NOT NULL,
	uid_foreign int(11) unsigned DEFAULT 0 NOT NULL,
	sorting     int(11) unsigned DEFAULT 0 NOT NULL,
	KEY         uid_local(uid_local),
	KEY         uid_foreign(uid_foreign)
);


#
# Table structure for table 'tx_seminars_seminars'
#
CREATE TABLE tx_seminars_seminars
(
	object_type                               int(11) unsigned    DEFAULT 0    NOT NULL,
	title                                     tinytext,
	topic                                     int(11) unsigned    DEFAULT 0    NOT NULL,
	slug                                      varchar(262),
	subtitle                                  tinytext,
	categories                                int(11) unsigned    DEFAULT 0    NOT NULL,
	teaser                                    text,
	description                               text,
	event_type                                int(11) unsigned    DEFAULT 0    NOT NULL,
	accreditation_number                      tinytext,
	credit_points                             int(11) unsigned    DEFAULT 0    NOT NULL,
	begin_date                                int(11) unsigned    DEFAULT 0    NOT NULL,
	end_date                                  int(11) unsigned    DEFAULT 0    NOT NULL,
	timeslots                                 int(3) unsigned     DEFAULT 0    NOT NULL,
	begin_date_registration                   int(11) unsigned    DEFAULT 0    NOT NULL,
	deadline_registration                     int(11) unsigned    DEFAULT 0    NOT NULL,
	deadline_early_bird                       int(11) unsigned    DEFAULT 0    NOT NULL,
	deadline_unregistration                   int(11) unsigned    DEFAULT 0    NOT NULL,
	download_start_date                       int(11) unsigned    DEFAULT 0    NOT NULL,
	billing_start                             int(11) unsigned    DEFAULT 0    NOT NULL,
	expiry                                    int(11) unsigned    DEFAULT 0    NOT NULL,
	details_page                              tinytext,
	place                                     int(11) unsigned    DEFAULT 0    NOT NULL,
	room                                      text,
	lodgings                                  int(11) unsigned    DEFAULT 0    NOT NULL,
	foods                                     int(11) unsigned    DEFAULT 0    NOT NULL,
	speakers                                  int(11) unsigned    DEFAULT 0    NOT NULL,
	partners                                  int(11) unsigned    DEFAULT 0    NOT NULL,
	tutors                                    int(11) unsigned    DEFAULT 0    NOT NULL,
	leaders                                   int(11) unsigned    DEFAULT 0    NOT NULL,
	price_regular                             decimal(10, 2) DEFAULT 0.0 NOT NULL,
	price_regular_early                       decimal(10, 2) DEFAULT 0.0 NOT NULL,
	price_special                             decimal(10, 2) DEFAULT 0.0 NOT NULL,
	price_special_early                       decimal(10, 2) DEFAULT 0.0 NOT NULL,
	additional_information                    text,
	payment_methods                           int(11) unsigned    DEFAULT 0    NOT NULL,
	organizers                                int(11) unsigned    DEFAULT 0   NOT NULL,
	organizing_partners                       int(11) unsigned    DEFAULT 0    NOT NULL,
	event_takes_place_reminder_sent           int(1) unsigned     DEFAULT 0    NOT NULL,
	cancelation_deadline_reminder_sent        int(1) unsigned     DEFAULT 0    NOT NULL,
	needs_registration                        tinyint(1) unsigned DEFAULT 0    NOT NULL,
	allows_multiple_registrations             tinyint(3) unsigned DEFAULT 0    NOT NULL,
	attendees_min                             int(11) unsigned    DEFAULT 0   NOT NULL,
	attendees_max                             int(11) unsigned    DEFAULT 0    NOT NULL,
	queue_size                                int(1) unsigned     DEFAULT 0    NOT NULL,
	offline_attendees                         int(11) unsigned    DEFAULT 0    NOT NULL,
	target_groups                             int(11) unsigned    DEFAULT 0    NOT NULL,
	#                                         @deprecated #1324 will be removed in seminars 6.0
	registrations                             int(11) unsigned    DEFAULT 0   NOT NULL,
	cancelled                                 tinyint(1) unsigned DEFAULT 0    NOT NULL,
	owner_feuser                              int(11) unsigned    DEFAULT 0    NOT NULL,
	vips                                      int(11) unsigned    DEFAULT 0    NOT NULL,
	checkboxes                                int(11) unsigned    DEFAULT 0    NOT NULL,
	uses_terms_2                              tinyint(1) unsigned DEFAULT 0    NOT NULL,
	notes                                     text,
	attached_files                            int(11) unsigned    DEFAULT 0    NOT NULL,
	image                                     int(11) unsigned    DEFAULT 0    NOT NULL,
	requirements                              int(11) unsigned    DEFAULT 0    NOT NULL,
	dependencies                              int(11) unsigned    DEFAULT 0    NOT NULL,
	organizers_notified_about_minimum_reached tinyint(1) unsigned DEFAULT 0    NOT NULL,
	mute_notification_emails                  tinyint(1) unsigned DEFAULT 0    NOT NULL,
	automatic_confirmation_cancelation        tinyint(1) unsigned DEFAULT 0    NOT NULL,
	price_on_request                          tinyint(1) unsigned DEFAULT 0    NOT NULL,
	date_of_last_registration_digest          int(11) unsigned    DEFAULT 0    NOT NULL,
	event_format                              int(1) unsigned     DEFAULT 0    NOT NULL,
	webinar_url                               tinytext,
	additional_email_text                     text,

	KEY                                       object_type(object_type),
	KEY                                       topic(topic),
	KEY                                       event_takes_place_reminder_sent(event_takes_place_reminder_sent),
	KEY                                       cancelation_deadline_reminder_sent(cancelation_deadline_reminder_sent),
	KEY                                       slug(slug(127)),
	FULLTEXT                                  index_event_searchfields(accreditation_number),
	FULLTEXT                                  index_topic_searchfields(title, subtitle, description)
) ENGINE = MyISAM;


#
# Table structure for table 'tx_seminars_speakers'
#
CREATE TABLE tx_seminars_speakers
(
	title              tinytext,
	organization       tinytext,
	homepage           tinytext,
	description        text,
	image              int(11) unsigned DEFAULT 0 NOT NULL,
	skills             int(11) unsigned DEFAULT 0 NOT NULL,
	notes              text,
	address            text,
	phone_work         tinytext,
	phone_home         tinytext,
	phone_mobile       tinytext,
	email              tinytext,
	cancelation_period int(11) unsigned DEFAULT 0 NOT NULL,

	FULLTEXT           index_searchfields(title)
) ENGINE = MyISAM;


#
# Table structure for table 'tx_seminars_attendances'
#
CREATE TABLE tx_seminars_attendances
(
	title                    tinytext,
	user                     int(11) unsigned    DEFAULT 0    NOT NULL,
	seminar                  int(11) unsigned    DEFAULT 0    NOT NULL,
	registration_queue       tinyint(1) unsigned DEFAULT 0    NOT NULL,
	price                    tinytext,
	price_code               tinytext,
	seats                    int(3) unsigned     DEFAULT 0    NOT NULL,
	registered_themselves    tinyint(1) unsigned DEFAULT 0    NOT NULL,
	total_price              decimal(10, 2) DEFAULT 0.0 NOT NULL,
	attendees_names          text,
	additional_persons       int(11) unsigned    DEFAULT 0    NOT NULL,
	invoice_date             int(11) unsigned    DEFAULT 0    NOT NULL,
	datepaid                 int(11) unsigned    DEFAULT 0    NOT NULL,
	invoice_number           varchar(8) NULL DEFAULT NULL,
	customer_number          varchar(8) NULL DEFAULT NULL,
	method_of_payment        int(11) unsigned    DEFAULT 0    NOT NULL,
	separate_billing_address tinyint(1) unsigned DEFAULT 1    NOT NULL,
	company                  varchar(80)    DEFAULT '',
	name                     varchar(80)    DEFAULT ''  NOT NULL,
	gender                   tinyint(1) unsigned DEFAULT 0    NOT NULL,
	address                  varchar(40)    DEFAULT '',
	zip                      varchar(10)    DEFAULT ''  NOT NULL,
	city                     varchar(40)    DEFAULT ''  NOT NULL,
	country                  varchar(40)    DEFAULT ''  NOT NULL,
	telephone                varchar(40)    DEFAULT ''  NOT NULL,
	email                    varchar(50)    DEFAULT ''  NOT NULL,
	been_there               tinyint(3) unsigned DEFAULT 0    NOT NULL,
	interests                text,
	expectations             text,
	background_knowledge     text,
	accommodation            text,
	lodgings                 int(11) unsigned    DEFAULT 0    NOT NULL,
	food                     text,
	foods                    int(11) unsigned    DEFAULT 0    NOT NULL,
	known_from               text,
	notes                    text,
	kids                     int(11) unsigned    DEFAULT 0    NOT NULL,
	checkboxes               int(11) unsigned    DEFAULT 0    NOT NULL,
	attendance_mode          int(1) unsigned     DEFAULT 0    NOT NULL,
	order_reference          tinytext,

	KEY                      seminar(seminar),
	KEY user(user)
);


#
# Table structure for table 'tx_seminars_sites'
#
CREATE TABLE tx_seminars_sites
(
	title          tinytext,
	address        text,
	city           tinytext,
	homepage       tinytext,
	directions     text,
	notes          text,
	contact_person tinytext,
	email_address  tinytext,
	phone_number   tinytext,

	FULLTEXT       index_searchfields(title, city)
) ENGINE = MyISAM;


#
# Table structure for table 'tx_seminars_organizers'
#
CREATE TABLE tx_seminars_organizers
(
	title        tinytext,
	homepage     tinytext,
	email        tinytext,
	email_footer text,
	description  text
);


#
# Table structure for table 'tx_seminars_payment_methods'
#
CREATE TABLE tx_seminars_payment_methods
(
	title       tinytext,
	description text
);


#
# Table structure for table 'tx_seminars_event_types'
#
CREATE TABLE tx_seminars_event_types
(
	title            tinytext,
	single_view_page int(11) unsigned DEFAULT 0 NOT NULL,

	FULLTEXT         index_searchfields(title)
) ENGINE = MyISAM;


#
# Table structure for table 'tx_seminars_checkboxes'
#
CREATE TABLE tx_seminars_checkboxes
(
	title       tinytext,
	description text
);


#
# Table structure for table 'tx_seminars_lodgings'
#
CREATE TABLE tx_seminars_lodgings
(
	title tinytext
);


#
# Table structure for table 'tx_seminars_foods'
#
CREATE TABLE tx_seminars_foods
(
	title tinytext
);


#
# Table structure for table 'tx_seminars_timeslots'
#
CREATE TABLE tx_seminars_timeslots
(
	seminar    int(11) unsigned DEFAULT 0 NOT NULL,
	begin_date int(11) unsigned DEFAULT 0 NOT NULL,
	end_date   int(11) unsigned DEFAULT 0 NOT NULL,
	place      int(11) unsigned DEFAULT 0 NOT NULL,
	room       text,

	KEY        seminar(seminar)
);


#
# Table structure for table 'tx_seminars_target_groups'
#
CREATE TABLE tx_seminars_target_groups
(
	title       tinytext,
	minimum_age tinyint(3) unsigned DEFAULT 0 NOT NULL,
	maximum_age tinyint(3) unsigned DEFAULT 0 NOT NULL,

	FULLTEXT    index_searchfields(title)
) ENGINE = MyISAM;


#
# Table structure for table 'tx_seminars_categories'
#
CREATE TABLE tx_seminars_categories
(
	title            tinytext,
	single_view_page int(11) unsigned DEFAULT 0 NOT NULL,

	FULLTEXT         index_searchfields(title)
) ENGINE = MyISAM;


#
# Table structure for table 'tx_seminars_skills'
#
CREATE TABLE tx_seminars_skills
(
	title tinytext
);
