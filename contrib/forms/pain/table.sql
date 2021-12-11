CREATE TABLE IF NOT EXISTS `form_pain` (
id bigint(20) NOT NULL auto_increment,
date datetime default NULL,
pid bigint(20) default NULL,
user varchar(255) default NULL,
groupname varchar(255) default NULL,
authorized tinyint(4) default NULL,
activity tinyint(4) default NULL,
history_of_pain longtext,
dull varchar(255),
colicky varchar(255),
sharp varchar(255),
duration_of_pain longtext,
pain_referred_to_other_sites longtext,
what_relieves_pain longtext,
what_makes_pain_worse longtext,
accompanying_symptoms_vomitting longtext,
accompanying_symptoms_nausea longtext,
accompanying_symptoms_headache longtext,
accompanying_symptoms_other longtext,
additional_notes longtext,
PRIMARY KEY (id)
) ENGINE=InnoDB;