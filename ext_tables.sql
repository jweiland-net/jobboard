#
# Table structure for table 'tx_jobfair2_domain_model_job'
#
CREATE TABLE tx_jobfair2_domain_model_job
(
	salary_min decimal(10, 2) DEFAULT '0.00' NOT NULL,
	salary_max decimal(10, 2) DEFAULT '0.00' NOT NULL
);

#
# Table structure for table 'tx_jobfair2_domain_model_salarygrade'
#
CREATE TABLE tx_jobfair2_domain_model_salarygrade
(
	flat_amount decimal(10, 2) DEFAULT '0.00' NOT NULL
);

#
# Table structure for table 'tx_jobfair2_domain_model_salarystep'
#
CREATE TABLE tx_jobfair2_domain_model_salarystep
(
	amount decimal(10, 2) DEFAULT '0.00' NOT NULL
);
