#
# Table structure for table 'tx_jobfair2_domain_model_job'
#
CREATE TABLE tx_jobfair2_domain_model_job
(
	title            varchar(250) DEFAULT ''  NOT NULL,
	reference_number varchar(120) DEFAULT ''  NOT NULL,
	is_import        tinyint(1) DEFAULT '0' NOT NULL,
	vacancy_id       varchar(30)  DEFAULT '0' NOT NULL,
	description      text,
	address          int(11) DEFAULT '0' NOT NULL,
	link             varchar(255) DEFAULT ''  NOT NULL,
	job_area         int(11) DEFAULT '0' NOT NULL,
	job_type         int(11) DEFAULT '0' NOT NULL,
	start_date       int(11) DEFAULT '0' NOT NULL,
	ending_date      int(11) DEFAULT '0' NOT NULL,
	employer         varchar(120) DEFAULT ''  NOT NULL,
	email            varchar(120) DEFAULT ''  NOT NULL,
	employer_address int(11) DEFAULT '0' NOT NULL,
	tender_file      int(11) DEFAULT '0' NOT NULL,
	pdf_files        int(11) DEFAULT '0' NOT NULL,
	pdf_tstamp       int(10) DEFAULT '0' NOT NULL,
	is_internal      tinyint(4) UNSIGNED DEFAULT '0' NOT NULL,
	salary_min       decimal(10,2) DEFAULT '0.00' NOT NULL,
	salary_max       decimal(10,2) DEFAULT '0.00' NOT NULL
);

#
# Table structure for table 'tx_jobfair2_domain_model_salarygrade'
#
CREATE TABLE tx_jobfair2_domain_model_salarygrade
(
	flat_amount decimal(10,2) DEFAULT '0.00' NOT NULL
);

#
# Table structure for table 'tx_jobfair2_domain_model_salarystep'
#
CREATE TABLE tx_jobfair2_domain_model_salarystep
(
	amount decimal(10,2) DEFAULT '0.00' NOT NULL
);

#
# Table structure for table 'tx_jobfair2_domain_model_jobarea'
#
CREATE TABLE tx_jobfair2_domain_model_jobarea
(
	title varchar(60) DEFAULT '' NOT NULL
);

#
# Table structure for table 'tx_jobfair2_domain_model_jobtype'
#
CREATE TABLE tx_jobfair2_domain_model_jobtype
(
	title varchar(60) DEFAULT '' NOT NULL
);

#
# Table structure for table 'tt_address'
#
CREATE TABLE tt_address
(
	import_key varchar(60) DEFAULT '' NOT NULL
);
