-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 23, 2020 at 02:04 PM
-- Server version: 10.2.25-MariaDB-log
-- PHP Version: 7.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------

--
-- Table structure for table Admins
--

CREATE TABLE Admins (
  Email varchar(64) NOT NULL,
  Name varchar(40) NOT NULL,
  Passhash varchar(64) NOT NULL,
  Superuser tinyint(1) NOT NULL DEFAULT 0,
  Token varchar(16) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table EmailSent
--

CREATE TABLE EmailSent (
  NameID int(11) NOT NULL,
  Year year(4) NOT NULL,
  Sent timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table MapscoData
--

CREATE TABLE MapscoData (
  City varchar(30) NOT NULL,
  StreetAddress varchar(100) NOT NULL,
  Mapsco varchar(10) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table Members
--

CREATE TABLE Members (
  FirstNames varchar(60) NOT NULL,
  LastName varchar(25) NOT NULL,
  StreetAddress varchar(100) NOT NULL,
  City varchar(30) NOT NULL,
  State varchar(2) NOT NULL,
  ZipCode varchar(10) NOT NULL,
  PhoneNumber varchar(32) DEFAULT NULL,
  Email1 varchar(64) DEFAULT NULL,
  Email2 varchar(64) DEFAULT NULL,
  Status varchar(20) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Used to import membership data';

-- --------------------------------------------------------

--
-- Table structure for table OrderDetails
--

CREATE TABLE OrderDetails (
  OrderNumber int(10) UNSIGNED NOT NULL,
  NameID int(11) NOT NULL,
  Reciprocity tinyint(1) NOT NULL DEFAULT 0,
  MemberStatus varchar(20) DEFAULT NULL,
  StaffMember tinyint(1) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table Orders
--

CREATE TABLE Orders (
  OrderNumber int(10) UNSIGNED NOT NULL,
  Year year(4) NOT NULL,
  NameID int(11) NOT NULL,
  MemberStatus varchar(20) DEFAULT NULL,
  StaffMember tinyint(1) DEFAULT NULL,
  AllMembers tinyint(1) NOT NULL DEFAULT 0,
  AllAssociates tinyint(1) NOT NULL DEFAULT 0,
  AllStaff tinyint(1) NOT NULL DEFAULT 0,
  Reciprocity tinyint(1) NOT NULL DEFAULT 0,
  ExtraBaskets int(10) UNSIGNED NOT NULL DEFAULT 0,
  ExtraDonation decimal(7,2) DEFAULT NULL,
  Subtotal decimal(7,2) DEFAULT NULL,
  PmtType enum('check','credit','echeck') DEFAULT NULL,
  TotalPaid decimal(7,2) DEFAULT NULL,
  PriceOverride decimal(7,2) DEFAULT NULL,
  Notes varchar(500) DEFAULT NULL,
  CustomName varchar(60) DEFAULT NULL,
  Driver tinyint(1) NOT NULL DEFAULT 0,
  Volunteer tinyint(1) NOT NULL DEFAULT 0,
  PhoneProvided varchar(32) DEFAULT NULL,
  PIN char(4) NOT NULL,
  LastUpdated timestamp NOT NULL DEFAULT current_timestamp(),
  Created timestamp NOT NULL DEFAULT current_timestamp(),
  PmtResult varchar(4000) DEFAULT NULL COMMENT 'Credit Card transaction response',
  PmtConfirmed tinyint(1) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table OrderWriteins
--

CREATE TABLE OrderWriteins (
  OrderNumber int(10) UNSIGNED NOT NULL,
  NameID int(11) DEFAULT NULL,
  Name varchar(60) NOT NULL,
  StreetAddress varchar(100) NOT NULL,
  City varchar(30) NOT NULL,
  State varchar(2) NOT NULL,
  ZipCode varchar(10) NOT NULL,
  PhoneNumber varchar(32) NOT NULL,
  Delivery enum('local','ship') NOT NULL,
  Seq int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table People
--

CREATE TABLE People (
  NameID int(11) NOT NULL,
  LastName varchar(25) NOT NULL,
  FirstNames varchar(60) NOT NULL,
  AndFamily tinyint(1) NOT NULL DEFAULT 0,
  StreetAddress varchar(100) NOT NULL DEFAULT '',
  City varchar(30) DEFAULT NULL,
  State varchar(2) DEFAULT NULL,
  ZipCode varchar(10) DEFAULT NULL,
  Mapsco varchar(10) DEFAULT NULL,
  DeliveryRoute varchar(40) NOT NULL DEFAULT '',
  PhoneNumber varchar(32) DEFAULT NULL,
  AltPhoneNumber varchar(32) DEFAULT NULL,
  Status varchar(20) NOT NULL DEFAULT 'Non-Member',
  Staff tinyint(1) NOT NULL DEFAULT 0,
  Invited tinyint(1) NOT NULL DEFAULT 0,
  Delivery tinyint(1) NOT NULL DEFAULT 1,
  OfficialLastName varchar(25) DEFAULT NULL,
  OfficialFirstNames varchar(50) DEFAULT NULL,
  Email varchar(64) DEFAULT NULL,
  Email2 varchar(64) DEFAULT NULL,
  LastUpdated timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table Prices
--

CREATE TABLE Prices (
  ID int(11) NOT NULL DEFAULT 1 COMMENT 'Must be 1',
  Basket decimal(5,2) NOT NULL COMMENT 'Name from checklist',
  Extra decimal(5,2) NOT NULL COMMENT 'Extra basket (self-pickup)',
  Local decimal(5,2) NOT NULL COMMENT 'Local non-member basket',
  Shipped decimal(5,2) NOT NULL COMMENT 'Non-member basket',
  Benefactor decimal(5,2) NOT NULL COMMENT 'Order for everyone'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='MUST HAVE ONLY 1 ROW';

-- --------------------------------------------------------
--
-- Views
--
-- --------------------------------------------------------

--
-- Stand-in structure for view BasketCounts
-- (See below for the actual view)
--
CREATE TABLE `BasketCounts` (
`Status` varchar(20)
,`Delivery` varchar(5)
,`Basket Count` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view BasketDeliveries
-- (See below for the actual view)
--
CREATE TABLE `BasketDeliveries` (
`NameID` int(11)
,`Route` varchar(40)
,`Last Name` varchar(25)
,`First Names` varchar(60)
,`Street Address` varchar(100)
,`City` varchar(30)
,`Zip` varchar(10)
,`Phone 1` varchar(32)
,`Phone 2` varchar(32)
,`Email 1` varchar(64)
,`Email 2` varchar(64)
,`Status` varchar(20)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view BasketShipments
-- (See below for the actual view)
--
CREATE TABLE `BasketShipments` (
`FirstNames` varchar(60)
,`LastName` varchar(25)
,`StreetAddress` varchar(100)
,`City` varchar(30)
,`State` varchar(2)
,`ZipCode` varchar(10)
,`Status` varchar(20)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view Benefactors
-- (See below for the actual view)
--
CREATE TABLE `Benefactors` (
`Name` varchar(87)
,`Phone 1` varchar(32)
,`Phone 2` varchar(32)
,`Email 1` varchar(64)
,`Email 2` varchar(64)
,`Year` year(4)
,`Former Status` varchar(20)
,`Current Status` varchar(20)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view BigWinners
-- (See below for the actual view)
--
CREATE TABLE `BigWinners` (
`Recipient` varchar(97)
,`Names` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view CardNames
-- (See below for the actual view)
--
CREATE TABLE `CardNames` (
`ForLast` varchar(25)
,`ForFirst` varchar(60)
,`ForName` varchar(97)
,`FromLast` varchar(25)
,`FromFirst` varchar(60)
,`FromName` varchar(99)
,`Benefactor` int(1)
,`Delivery` tinyint(1)
,`Mapsco` varchar(10)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view CardNames2
-- (See below for the actual view)
--
CREATE TABLE `CardNames2` (
`ForLast` varchar(25)
,`ForFirst` varchar(60)
,`ForName` varchar(97)
,`FromLast` varchar(25)
,`FromFirst` varchar(60)
,`FromName` varchar(97)
,`Benefactor` int(1)
,`Delivery` tinyint(1)
,`Route` varchar(40)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view CardNameSummary
-- (See below for the actual view)
--
CREATE TABLE `CardNameSummary` (
`ForName` varchar(97)
,`FromNames` mediumtext
,`Mapsco` varchar(10)
,`NameCount` bigint(21)
,`BeneCount` decimal(32,0)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view CardNameSummary2
-- (See below for the actual view)
--
CREATE TABLE `CardNameSummary2` (
`ForName` varchar(97)
,`NameList` longtext
,`BeneList` longtext
,`NameCount` bigint(21)
,`BeneCount` bigint(21)
,`Route` varchar(40)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view Checklist
-- (See below for the actual view)
--
CREATE TABLE `Checklist` (
`Name` varchar(91)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view Drivers
-- (See below for the actual view)
--
CREATE TABLE `Drivers` (
`FirstNames` varchar(60)
,`LastName` varchar(25)
,`StreetAddress` varchar(100)
,`City` varchar(30)
,`Email` varchar(64)
,`PhoneNumber` varchar(32)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view LargeNonBenefactorOrdersFromLastYear
-- (See below for the actual view)
--
CREATE TABLE `LargeNonBenefactorOrdersFromLastYear` (
`FirstNames` varchar(60)
,`LastName` varchar(25)
,`PhoneNumber` varchar(32)
,`Baskets` bigint(21)
,`Subtotal` decimal(7,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view LocalShipments
-- (See below for the actual view)
--
CREATE TABLE `LocalShipments` (
`NameID` int(11)
,`FirstNames` varchar(60)
,`LastName` varchar(25)
,`StreetAddress` varchar(100)
,`City` varchar(30)
,`Status` varchar(20)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view MembershipCounts
-- (See below for the actual view)
--
CREATE TABLE `MembershipCounts` (
`Status` varchar(20)
,`Type` varchar(9)
,`Count` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view MissingMapscos
-- (See below for the actual view)
--
CREATE TABLE `MissingMapscos` (
`NameID` int(11)
,`LastName` varchar(25)
,`FirstNames` varchar(60)
,`StreetAddress` varchar(100)
,`City` varchar(30)
,`State` varchar(2)
,`ZipCode` varchar(10)
,`Mapsco` varchar(10)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view NonMemberBasketsSold
-- (See below for the actual view)
--
CREATE TABLE `NonMemberBasketsSold` (
`Year` year(4)
,`Total` bigint(21)
,`Uniq` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view OrdersFromLastYear
-- (See below for the actual view)
--
CREATE TABLE `OrdersFromLastYear` (
`NameID` int(11)
,`FirstNames` varchar(60)
,`LastName` varchar(25)
,`AddressLine1` varchar(100)
,`AddressLine2` varchar(46)
,`PhoneNumber` varchar(32)
,`Email` varchar(64)
,`Email2` varchar(64)
,`PIN` char(4)
,`Benefactor` varchar(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view OrdersFromTwoYearsAgo
-- (See below for the actual view)
--
CREATE TABLE `OrdersFromTwoYearsAgo` (
`NameID` int(11)
,`FirstNames` varchar(60)
,`LastName` varchar(25)
,`AddressLine1` varchar(100)
,`AddressLine2` varchar(46)
,`Email` varchar(64)
,`Email2` varchar(64)
,`PIN` char(4)
,`Benefactor` varchar(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view OrdersPlaced
-- (See below for the actual view)
--
CREATE TABLE `OrdersPlaced` (
`NameID` int(11)
,`OrderNumber` int(10) unsigned
);

-- --------------------------------------------------------

--
-- Stand-in structure for view OrderSummary
-- (See below for the actual view)
--
CREATE TABLE `OrderSummary` (
`Order #` int(10) unsigned
,`First Name(s)` varchar(60)
,`Last Name(s)` varchar(25)
,`Phone Number` varchar(32)
,`E-mail Address` varchar(64)
,`Benefactor` varchar(1)
,`Reciprocity` varchar(1)
,`Subtotal` decimal(7,2)
,`Added Donation` decimal(7,2)
,`Total Due` decimal(8,2)
,`Pmt Type` enum('check','credit','echeck')
,`Amt Paid` decimal(7,2)
,`Balance` decimal(9,2)
,`Pmt Rcvd` varchar(1)
,`Notes or Special Instructions` varchar(500)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view OutsideDeliveryArea
-- (See below for the actual view)
--
CREATE TABLE `OutsideDeliveryArea` (
`StreetAddress` varchar(100)
,`City` varchar(30)
,`FirstNames` varchar(60)
,`LastName` varchar(25)
,`Delivery` tinyint(1)
,`Mapsco` varchar(10)
,`Status` varchar(20)
,`PhoneNumber` varchar(32)
,`Email` varchar(64)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view PotentialInvitees
-- (See below for the actual view)
--
CREATE TABLE `PotentialInvitees` (
`OrderCount` bigint(21)
,`LastName` varchar(25)
,`FirstNames` varchar(60)
,`AndFamily` tinyint(1)
,`StreetAddress` varchar(100)
,`City` varchar(30)
,`State` varchar(2)
,`ZipCode` varchar(10)
,`Email` varchar(64)
,`NameID` int(11)
,`Invited` tinyint(1)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view Reciprocity-Preview
-- (See below for the actual view)
--
CREATE TABLE `Reciprocity-Preview` (
`FromID` int(11)
,`FromLast` varchar(25)
,`FromFirst` varchar(60)
,`NeededForID` int(11)
,`NeededForLast` varchar(25)
,`NeededForFirst` varchar(60)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view Reciprocity-PreviewCount
-- (See below for the actual view)
--
CREATE TABLE `Reciprocity-PreviewCount` (
`count(*)` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view Reciprocity-Review
-- (See below for the actual view)
--
CREATE TABLE `Reciprocity-Review` (
`OrderNumber` int(10) unsigned
,`FromLast` varchar(25)
,`FromFirst` varchar(60)
,`ForLast` varchar(25)
,`ForFirst` varchar(60)
,`Phone` varchar(32)
,`AltPhone` varchar(32)
,`Email1` varchar(64)
,`Email2` varchar(64)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view Reciprocity-ReviewSummary
-- (See below for the actual view)
--
CREATE TABLE `Reciprocity-ReviewSummary` (
`OrderNumber` int(10) unsigned
,`From` varchar(87)
,`For` mediumtext
,`Count` bigint(21)
,`Phone` varchar(32)
,`AltPhone` varchar(32)
,`Email1` varchar(64)
,`Email2` varchar(64)
,`PmtType` enum('check','credit','echeck')
,`NameID` int(11)
,`PIN` char(4)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view SelfPickup
-- (See below for the actual view)
--
CREATE TABLE `SelfPickup` (
`Last Name` varchar(25)
,`First Name(s)` varchar(60)
,`Email Address` varchar(64)
,`Phone Number` varchar(32)
,`Extra Baskets` int(10) unsigned
,`Route` varchar(40)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view SelfPickupCount
-- (See below for the actual view)
--
CREATE TABLE `SelfPickupCount` (
`Families` bigint(21)
,`Total Baskets` decimal(32,0)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view Volunteers
-- (See below for the actual view)
--
CREATE TABLE `Volunteers` (
`FirstNames` varchar(60)
,`LastName` varchar(25)
,`Email` varchar(64)
,`PhoneNumber` varchar(32)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view _CardNames2Rollup
-- (See below for the actual view)
--
CREATE TABLE `_CardNames2Rollup` (
`ForName` varchar(97)
,`FromNames` mediumtext
,`NameCount` bigint(21)
,`Benefactor` int(1)
,`Route` varchar(40)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view _CostCalculator
-- (See below for the actual view)
--
CREATE TABLE `_CostCalculator` (
`OrderNumber` int(10) unsigned
,`Benefactor` tinyint(1)
,`NumListed` decimal(23,0)
,`NumLocal` decimal(23,0)
,`NumShip` decimal(23,0)
,`NumExtra` int(10) unsigned
,`OldTotal` decimal(7,2)
,`NewTotal` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view _DeliveryData
-- (See below for the actual view)
--
CREATE TABLE `_DeliveryData` (
`NameID` int(11)
,`Status` varchar(20)
,`Delivery` varchar(5)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view _ExtractNumFromMapsco
-- (See below for the actual view)
--
CREATE TABLE `_ExtractNumFromMapsco` (
`NameID` int(11)
,`Mapsco` varchar(10)
,`Value` varchar(12)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view _OrderCostData
-- (See below for the actual view)
--
CREATE TABLE `_OrderCostData` (
`OrderNumber` int(10) unsigned
,`Benefactor` tinyint(1)
,`NumListed` decimal(23,0)
,`NumLocal` decimal(23,0)
,`NumShip` decimal(23,0)
,`NumExtra` int(10) unsigned
,`OldTotal` decimal(7,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view _OrderFromTo
-- (See below for the actual view)
--
CREATE TABLE `_OrderFromTo` (
`OrderedBy` int(11)
,`OrderedFor` int(11)
);

-- --------------------------------------------------------

--
-- Structure for view BasketCounts
--
DROP TABLE IF EXISTS `BasketCounts`;

CREATE VIEW BasketCounts  AS  select `_DeliveryData`.`Status` AS `Status`,
`_DeliveryData`.`Delivery` AS `Delivery`,
count(`_DeliveryData`.`NameID`) AS `Basket Count`
from `_DeliveryData` group by `_DeliveryData`.`Delivery`,`_DeliveryData`.`Status` with rollup ;

-- --------------------------------------------------------

--
-- Structure for view BasketDeliveries
--
DROP TABLE IF EXISTS `BasketDeliveries`;

CREATE VIEW BasketDeliveries  AS  select p.NameID AS NameID,p.DeliveryRoute AS Route,p.LastName AS `Last Name`,
p.FirstNames AS `First Names`,p.StreetAddress AS `Street Address`,p.City AS City,
p.ZipCode AS Zip,ifnull(p.PhoneNumber,'') AS `Phone 1`,ifnull(p.AltPhoneNumber,'') AS `Phone 2`,
ifnull(p.Email,'') AS `Email 1`,ifnull(p.Email2,'') AS `Email 2`,p.`Status` AS `Status`
from ((People p left join OrderDetails on(p.NameID = OrderDetails.NameID))
left join Orders on(Orders.OrderNumber = OrderDetails.OrderNumber))
where p.Delivery = 1 and (p.`Status` <> 'Non-Member' or p.Staff = 1 or
OrderDetails.NameID is not null and Orders.`Year` = year(curdate()))
group by p.NameID order by p.DeliveryRoute,p.LastName,p.FirstNames ;

-- --------------------------------------------------------

--
-- Structure for view BasketShipments
--
DROP TABLE IF EXISTS `BasketShipments`;

CREATE VIEW BasketShipments  AS  select p.FirstNames AS FirstNames,p.LastName AS LastName,p.StreetAddress AS StreetAddress,p.City AS City,p.State AS State,p.ZipCode AS ZipCode,p.`Status` AS `Status` from ((People p left join OrderDetails on(p.NameID = OrderDetails.NameID)) left join Orders on(Orders.OrderNumber = OrderDetails.OrderNumber)) where p.Delivery = 0 and (p.`Status` <> _utf8'Non-Member' or p.Staff = 1 or OrderDetails.NameID is not null and Orders.`Year` = year(curdate())) group by p.NameID order by p.LastName,p.FirstNames ;

-- --------------------------------------------------------

--
-- Structure for view Benefactors
--
DROP TABLE IF EXISTS `Benefactors`;

CREATE VIEW Benefactors  AS  select concat(p.LastName,', ',p.FirstNames) AS `Name`,p.PhoneNumber AS `Phone 1`,p.AltPhoneNumber AS `Phone 2`,p.Email AS `Email 1`,p.Email2 AS `Email 2`,o.`Year` AS `Year`,o.MemberStatus AS `Former Status`,p.`Status` AS `Current Status` from (Orders o join People p) where o.NameID = p.NameID and o.AllMembers = 1 order by o.`Year`,p.LastName,p.FirstNames ;

-- --------------------------------------------------------

--
-- Structure for view BigWinners
--
DROP TABLE IF EXISTS `BigWinners`;

CREATE VIEW BigWinners  AS  select CardNames.ForName AS Recipient,count(CardNames.FromLast) AS `Names` from CardNames group by CardNames.ForLast,CardNames.ForFirst order by count(CardNames.FromLast) desc ;

-- --------------------------------------------------------

--
-- Structure for view CardNames
--
DROP TABLE IF EXISTS `CardNames`;

CREATE VIEW CardNames  AS  select p2.LastName AS ForLast,p2.FirstNames AS ForFirst,concat(p2.FirstNames,_utf8' ',p2.LastName,if(p2.AndFamily,_utf8' and Family',_utf8'')) AS ForName,p1.LastName AS FromLast,p1.FirstNames AS FromFirst,concat(if(Orders.CustomName is not null and length(Orders.CustomName) > 0,Orders.CustomName,concat(p1.FirstNames,_utf8' ',p1.LastName,if(p1.AndFamily,_utf8' and Family',_utf8''))),if(Orders.AllMembers = 1 and (p2.`Status` = _utf8'Member' or p2.`Status` = _utf8'College') or Orders.AllAssociates = 1 and p2.`Status` = _utf8'Associate' or Orders.AllStaff = 1 and p2.Staff = 1,_utf8' *',_utf8'')) AS FromName,if(Orders.AllMembers = 1 and (p2.`Status` = _utf8'Member' or p2.`Status` = _utf8'College') or Orders.AllAssociates = 1 and p2.`Status` = _utf8'Associate' or Orders.AllStaff = 1 and p2.Staff = 1,1,0) AS Benefactor,p2.Delivery AS Delivery,p2.Mapsco AS Mapsco from (((People p1 join People p2) join Orders on(Orders.NameID = p1.NameID)) left join OrderDetails on(Orders.OrderNumber = OrderDetails.OrderNumber and OrderDetails.NameID = p2.NameID)) where Orders.`Year` = year(curdate()) and (OrderDetails.NameID is not null or Orders.AllMembers = 1 and (p2.`Status` = _utf8'Member' or p2.`Status` = _utf8'College') or Orders.AllAssociates = 1 and p2.`Status` = _utf8'Associate' or Orders.AllStaff = 1 and p2.Staff = 1) order by p2.Delivery,p2.DeliveryRoute,p2.Mapsco,p2.LastName,p2.FirstNames,p1.LastName,p1.FirstNames ;

-- --------------------------------------------------------

--
-- Structure for view CardNames2
--
DROP TABLE IF EXISTS `CardNames2`;

CREATE VIEW CardNames2  AS  select p2.LastName AS ForLast,p2.FirstNames AS ForFirst,concat(p2.FirstNames,' ',p2.LastName,if(p2.AndFamily,' and Family','')) AS ForName,p1.LastName AS FromLast,p1.FirstNames AS FromFirst,concat(if(Orders.CustomName is not null and length(Orders.CustomName) > 0,Orders.CustomName,concat(p1.FirstNames,' ',p1.LastName,if(p1.AndFamily,' and Family','')))) AS FromName,if(Orders.AllMembers = 1 and (p2.`Status` = 'Member' or p2.`Status` = 'College') or Orders.AllAssociates = 1 and p2.`Status` = 'Associate' or Orders.AllStaff = 1 and p2.Staff = 1,1,0) AS Benefactor,p2.Delivery AS Delivery,p2.DeliveryRoute AS Route from (((People p1 join People p2) join Orders on(Orders.NameID = p1.NameID)) left join OrderDetails on(Orders.OrderNumber = OrderDetails.OrderNumber and OrderDetails.NameID = p2.NameID)) where Orders.`Year` = year(curdate()) and (OrderDetails.NameID is not null or Orders.AllMembers = 1 and (p2.`Status` = 'Member' or p2.`Status` = 'College') or Orders.AllAssociates = 1 and p2.`Status` = 'Associate' or Orders.AllStaff = 1 and p2.Staff = 1) order by p2.Delivery,p2.DeliveryRoute,p2.LastName,p2.FirstNames,p1.LastName,p1.FirstNames ;

-- --------------------------------------------------------

--
-- Structure for view CardNameSummary
--
DROP TABLE IF EXISTS `CardNameSummary`;

CREATE VIEW CardNameSummary  AS  select CardNames.ForName AS ForName,group_concat(CardNames.FromName order by CardNames.FromLast ASC,CardNames.FromFirst ASC separator '\n') AS FromNames,if(CardNames.Delivery,CardNames.Mapsco,'') AS Mapsco,count(CardNames.FromName) AS NameCount,sum(CardNames.Benefactor) AS BeneCount from CardNames group by CardNames.ForLast,CardNames.ForFirst ;

-- --------------------------------------------------------

--
-- Structure for view CardNameSummary2
--
DROP TABLE IF EXISTS `CardNameSummary2`;

CREATE VIEW CardNameSummary2  AS  select distinct c1.ForName AS ForName,ifnull(c2.FromNames,'') AS NameList,ifnull(c3.FromNames,'') AS BeneList,ifnull(c2.NameCount,0) AS NameCount,ifnull(c3.NameCount,0) AS BeneCount,c1.Route AS Route from ((_CardNames2Rollup c1 left join _CardNames2Rollup c2 on(c1.ForName = c2.ForName and c2.Benefactor = 0)) left join _CardNames2Rollup c3 on(c1.ForName = c3.ForName and c3.Benefactor = 1)) ;

-- --------------------------------------------------------

--
-- Structure for view Checklist
--
DROP TABLE IF EXISTS `Checklist`;

CREATE VIEW Checklist  AS  select concat(_utf8'___ ',People.LastName,_utf8', ',People.FirstNames) AS `Name` from People where People.`Status` = _utf8'Member' or People.`Status` = _utf8'College' or People.`Status` = _utf8'Associate' or People.Staff = 1 order by if(People.`Status` = _utf8'Member',1,if(People.`Status` = _utf8'Associate',2,if(People.`Status` = _utf8'College',3,4))),People.LastName,People.FirstNames ;

-- --------------------------------------------------------

--
-- Structure for view Drivers
--
DROP TABLE IF EXISTS `Drivers`;

CREATE VIEW Drivers  AS  select People.FirstNames AS FirstNames,People.LastName AS LastName,People.StreetAddress AS StreetAddress,People.City AS City,People.Email AS Email,People.PhoneNumber AS PhoneNumber from (People join Orders on(People.NameID = Orders.NameID)) where Orders.Driver = 1 and Orders.`Year` = year(curdate()) order by People.LastName,People.FirstNames ;

-- --------------------------------------------------------

--
-- Structure for view Invitees
--
DROP TABLE IF EXISTS `Invitees`;

CREATE VIEW Invitees  AS  select People.LastName AS LastName,People.FirstNames AS FirstNames,People.Email AS Email,People.Email2 AS Email2 from People where People.Invited = 1 and People.`Status` = 'Non-Member' and People.Staff = 0 and People.Email is not null and People.Email <> '' order by 1,2 ;

-- --------------------------------------------------------

--
-- Structure for view LargeNonBenefactorOrdersFromLastYear
--
DROP TABLE IF EXISTS `LargeNonBenefactorOrdersFromLastYear`;

CREATE VIEW LargeNonBenefactorOrdersFromLastYear  AS  select p.FirstNames AS FirstNames,p.LastName AS LastName,p.PhoneNumber AS PhoneNumber,count(od.NameID) AS Baskets,o.Subtotal AS Subtotal from ((Orders o join People p on(o.NameID = p.NameID)) join OrderDetails od on(o.OrderNumber = od.OrderNumber)) where o.`Year` = year(curdate()) - 1 and o.AllMembers = 0 and (p.`Status` = _utf8'Member' or p.`Status` = _utf8'Member' or p.Staff = 1) group by od.OrderNumber order by o.Subtotal desc,p.LastName,p.FirstNames ;

-- --------------------------------------------------------

--
-- Structure for view LocalShipments
--
DROP TABLE IF EXISTS `LocalShipments`;

CREATE VIEW LocalShipments  AS  select p.NameID AS NameID,p.FirstNames AS FirstNames,p.LastName AS LastName,p.StreetAddress AS StreetAddress,p.City AS City,p.`Status` AS `Status` from ((People p left join OrderDetails on(p.NameID = OrderDetails.NameID)) left join Orders on(Orders.OrderNumber = OrderDetails.OrderNumber)) where (OrderDetails.NameID is not null or p.`Status` <> 'Non-Member') and p.Delivery = 0 and Orders.`Year` = year(curdate()) and p.State = 'TX' and p.City in ('Dallas','Plano','Addison','Richardson','Carrollton','Frisco','McKinney','Allen','The Colony') order by p.City,p.LastName,p.FirstNames ;

-- --------------------------------------------------------

--
-- Structure for view MembershipCounts
--
DROP TABLE IF EXISTS `MembershipCounts`;

CREATE VIEW MembershipCounts  AS  select People.`Status` AS `Status`,if(People.Staff is not null,if(People.Staff,_utf8'Staff',_utf8'Non-Staff'),_utf8'Total') AS `Type`,count(People.LastName) AS Count from People group by People.`Status`,People.Staff with rollup ;

-- --------------------------------------------------------

--
-- Structure for view MissingMapscos
--
DROP TABLE IF EXISTS `MissingMapscos`;

CREATE VIEW MissingMapscos  AS  select distinct People.NameID AS NameID,People.LastName AS LastName,People.FirstNames AS FirstNames,People.StreetAddress AS StreetAddress,People.City AS City,People.State AS State,People.ZipCode AS ZipCode,People.Mapsco AS Mapsco from ((People left join OrderDetails on(People.NameID = OrderDetails.NameID)) left join Orders on(Orders.OrderNumber = OrderDetails.OrderNumber)) where People.Delivery = 1 and (People.`Status` = _utf8'Non-Member' and OrderDetails.NameID is not null and Orders.`Year` = year(curdate()) and (People.Mapsco is null or People.Mapsco = _utf8'') or (People.`Status` <> _utf8'Non-Member' or People.Staff = 1) and (People.Mapsco is null or People.Mapsco = _utf8'')) order by People.City,People.ZipCode,People.LastName,People.FirstNames ;

-- --------------------------------------------------------

--
-- Structure for view NonMemberBasketsSold
--
DROP TABLE IF EXISTS `NonMemberBasketsSold`;

CREATE VIEW NonMemberBasketsSold  AS  select o.`Year` AS `Year`,count(ow.NameID) AS Total,count(distinct ow.NameID) AS Uniq from (OrderWriteins ow join Orders o on(o.OrderNumber = ow.OrderNumber)) group by o.`Year` order by o.`Year` ;

-- --------------------------------------------------------

--
-- Structure for view OrdersFromLastYear
--
DROP TABLE IF EXISTS `OrdersFromLastYear`;

CREATE VIEW OrdersFromLastYear  AS  select p.NameID AS NameID,p.FirstNames AS FirstNames,p.LastName AS LastName,p.StreetAddress AS AddressLine1,if(length(p.ZipCode) > 0,concat(p.City,_utf8', ',p.State,_utf8'  ',p.ZipCode),_utf8'') AS AddressLine2,p.PhoneNumber AS PhoneNumber,p.Email AS Email,p.Email2 AS Email2,o.PIN AS PIN,if(o.AllMembers,_utf8'Y',_utf8'N') AS Benefactor from (People p join Orders o on(p.NameID = o.NameID)) where o.`Year` = year(curdate()) - 1 and (p.`Status` = _utf8'Member' or p.`Status` = _utf8'Associate' or p.Staff = 1) order by p.LastName,p.FirstNames ;

-- --------------------------------------------------------

--
-- Structure for view OrdersFromTwoYearsAgo
--
DROP TABLE IF EXISTS `OrdersFromTwoYearsAgo`;

CREATE VIEW OrdersFromTwoYearsAgo  AS  select p.NameID AS NameID,p.FirstNames AS FirstNames,p.LastName AS LastName,p.StreetAddress AS AddressLine1,concat(p.City,_utf8', ',p.State,_utf8'  ',p.ZipCode) AS AddressLine2,p.Email AS Email,p.Email2 AS Email2,o1.PIN AS PIN,if(o1.AllMembers,_utf8'Y',_utf8'N') AS Benefactor from ((Orders o1 left join Orders o2 on(o1.NameID = o2.NameID and o1.`Year` = year(curdate()) - 2 and o2.`Year` = year(curdate()) - 1)) join People p on(o1.NameID = p.NameID)) where o1.`Year` = year(curdate()) - 2 and o2.OrderNumber is null and (p.`Status` = _utf8'Member' or p.`Status` = _utf8'Associate' or p.Staff = 1) order by p.LastName,p.FirstNames ;

-- --------------------------------------------------------

--
-- Structure for view OrdersPlaced
--
DROP TABLE IF EXISTS `OrdersPlaced`;

CREATE VIEW OrdersPlaced  AS  select Orders.NameID AS NameID,Orders.OrderNumber AS OrderNumber from Orders where Orders.`Year` = year(curdate()) order by 1 ;

-- --------------------------------------------------------

--
-- Structure for view OrderSummary
--
DROP TABLE IF EXISTS `OrderSummary`;

CREATE VIEW OrderSummary  AS  select Orders.OrderNumber AS `Order #`,People.FirstNames AS `First Name(s)`,People.LastName AS `Last Name(s)`,People.PhoneNumber AS `Phone Number`,People.Email AS `E-mail Address`,if(Orders.AllMembers,'Y','') AS Benefactor,if(Orders.Reciprocity,'Y','') AS Reciprocity,ifnull(Orders.Subtotal,0) AS Subtotal,ifnull(Orders.ExtraDonation,0) AS `Added Donation`,ifnull(Orders.Subtotal,0) + ifnull(Orders.ExtraDonation,0) AS `Total Due`,Orders.PmtType AS `Pmt Type`,ifnull(Orders.TotalPaid,0) AS `Amt Paid`,ifnull(Orders.Subtotal,0) + ifnull(Orders.ExtraDonation,0) - ifnull(Orders.TotalPaid,0) AS Balance,if(Orders.PmtConfirmed,'Y','') AS `Pmt Rcvd`,ifnull(Orders.Notes,'') AS `Notes or Special Instructions` from (Orders join People on(Orders.NameID = People.NameID)) where Orders.`Year` = year(curdate()) order by Orders.OrderNumber ;

-- --------------------------------------------------------

--
-- Structure for view OutsideDeliveryArea
--
DROP TABLE IF EXISTS `OutsideDeliveryArea`;

CREATE VIEW OutsideDeliveryArea  AS  select People.StreetAddress AS StreetAddress,People.City AS City,People.FirstNames AS FirstNames,People.LastName AS LastName,People.Delivery AS Delivery,People.Mapsco AS Mapsco,People.`Status` AS `Status`,People.PhoneNumber AS PhoneNumber,People.Email AS Email from People where People.City <> _utf8'Dallas' and People.City <> _utf8'Plano' and People.City <> _utf8'Addison' and People.City <> _utf8'Richardson' and People.City <> _utf8'Carrollton' and People.City <> _utf8'Frisco' and People.City <> _utf8'McKinney' and People.City <> _utf8'Allen' and People.City <> _utf8'The Colony' and People.Delivery = 1 and People.State = _utf8'TX' ;

-- --------------------------------------------------------

--
-- Structure for view PotentialInvitees
--
DROP TABLE IF EXISTS `PotentialInvitees`;

CREATE VIEW PotentialInvitees  AS  select count(0) AS OrderCount,People.LastName AS LastName,People.FirstNames AS FirstNames,People.AndFamily AS AndFamily,People.StreetAddress AS StreetAddress,People.City AS City,People.State AS State,People.ZipCode AS ZipCode,People.Email AS Email,People.NameID AS NameID,People.Invited AS Invited from ((People join OrderDetails on(OrderDetails.NameID = People.NameID)) join Orders on(Orders.OrderNumber = OrderDetails.OrderNumber)) where People.Invited = 0 and Orders.`Year` = year(curdate()) - 1 and People.`Status` = _utf8'Non-Member' and People.Staff = 0 group by People.NameID order by count(0) desc,People.LastName,People.FirstNames ;

-- --------------------------------------------------------

--
-- Structure for view Reciprocity-Preview
--
DROP TABLE IF EXISTS `Reciprocity-Preview`;

CREATE VIEW `Reciprocity-Preview`  AS  select P1.NameID AS FromID,P1.LastName AS FromLast,P1.FirstNames AS FromFirst,P2.NameID AS NeededForID,P2.LastName AS NeededForLast,P2.FirstNames AS NeededForFirst from ((((_OrderFromTo join Orders on(_OrderFromTo.OrderedFor = Orders.NameID)) join People P2 on(_OrderFromTo.OrderedBy = P2.NameID)) join People P1 on(_OrderFromTo.OrderedFor = P1.NameID)) left join _OrderFromTo OFT2 on(_OrderFromTo.OrderedFor = OFT2.OrderedBy)) where Orders.`Year` = year(curdate()) and Orders.Reciprocity = 1 and P2.`Status` <> _utf8'Non-Member' group by _OrderFromTo.OrderedFor,_OrderFromTo.OrderedBy having sum(if(OFT2.OrderedFor is not null and _OrderFromTo.OrderedBy = OFT2.OrderedFor,1,0)) = 0 order by P1.LastName,P1.FirstNames,P2.LastName,P2.FirstNames ;

-- --------------------------------------------------------

--
-- Structure for view Reciprocity-PreviewCount
--
DROP TABLE IF EXISTS `Reciprocity-PreviewCount`;

CREATE VIEW `Reciprocity-PreviewCount`  AS  select count(0) AS `count(*)` from `Reciprocity-Preview` where 1 ;

-- --------------------------------------------------------

--
-- Structure for view Reciprocity-Review
--
DROP TABLE IF EXISTS `Reciprocity-Review`;

CREATE VIEW `Reciprocity-Review`  AS  select Orders.OrderNumber AS OrderNumber,P1.LastName AS FromLast,P1.FirstNames AS FromFirst,P2.LastName AS ForLast,P2.FirstNames AS ForFirst,P1.PhoneNumber AS Phone,P1.AltPhoneNumber AS AltPhone,P1.Email AS Email1,P1.Email2 AS Email2 from (((Orders join OrderDetails on(Orders.OrderNumber = OrderDetails.OrderNumber)) join People P1 on(Orders.NameID = P1.NameID)) join People P2 on(OrderDetails.NameID = P2.NameID)) where Orders.`Year` = year(curdate()) and OrderDetails.Reciprocity = 1 order by P1.LastName,P1.FirstNames,P2.LastName,P2.FirstNames ;

-- --------------------------------------------------------

--
-- Structure for view Reciprocity-ReviewSummary
--
DROP TABLE IF EXISTS `Reciprocity-ReviewSummary`;

CREATE VIEW `Reciprocity-ReviewSummary`  AS  select `Reciprocity-Review`.OrderNumber AS OrderNumber,concat(`Reciprocity-Review`.FromLast,_utf8', ',`Reciprocity-Review`.FromFirst) AS `From`,group_concat(concat(`Reciprocity-Review`.ForLast,_utf8', ',`Reciprocity-Review`.ForFirst) order by `Reciprocity-Review`.ForLast ASC,`Reciprocity-Review`.ForFirst ASC separator '\n') AS `For`,count(`Reciprocity-Review`.ForLast) AS Count,`Reciprocity-Review`.Phone AS Phone,`Reciprocity-Review`.AltPhone AS AltPhone,`Reciprocity-Review`.Email1 AS Email1,`Reciprocity-Review`.Email2 AS Email2,Orders.PmtType AS PmtType,Orders.NameID AS NameID,Orders.PIN AS PIN from (`Reciprocity-Review` join Orders on(`Reciprocity-Review`.OrderNumber = Orders.OrderNumber)) group by `Reciprocity-Review`.OrderNumber ;

-- --------------------------------------------------------

--
-- Structure for view SelfPickup
--
DROP TABLE IF EXISTS `SelfPickup`;

CREATE VIEW SelfPickup  AS  select People.LastName AS `Last Name`,People.FirstNames AS `First Name(s)`,People.Email AS `Email Address`,People.PhoneNumber AS `Phone Number`,Orders.ExtraBaskets AS `Extra Baskets`,People.DeliveryRoute AS Route from (People join Orders on(People.NameID = Orders.NameID)) where Orders.`Year` = year(curdate()) and (People.Mapsco = _utf8'AT' and People.Staff = 0 or Orders.ExtraBaskets > 0) order by People.LastName,People.FirstNames ;

-- --------------------------------------------------------

--
-- Structure for view SelfPickupCount
--
DROP TABLE IF EXISTS `SelfPickupCount`;

CREATE VIEW SelfPickupCount  AS  select count(SelfPickup.`Last Name`) AS Families,sum(SelfPickup.`Extra Baskets`) AS `Total Baskets` from SelfPickup ;

-- --------------------------------------------------------

--
-- Structure for view Volunteers
--
DROP TABLE IF EXISTS `Volunteers`;

CREATE VIEW Volunteers  AS  select People.FirstNames AS FirstNames,People.LastName AS LastName,People.Email AS Email,People.PhoneNumber AS PhoneNumber from (People join Orders on(People.NameID = Orders.NameID)) where Orders.Volunteer = 1 and Orders.`Year` = year(curdate()) order by People.LastName,People.FirstNames ;

-- --------------------------------------------------------

--
-- Structure for view _CardNames2Rollup
--
DROP TABLE IF EXISTS `_CardNames2Rollup`;

CREATE VIEW _CardNames2Rollup  AS  select CardNames2.ForName AS ForName,group_concat(CardNames2.FromName order by CardNames2.FromLast ASC,CardNames2.FromFirst ASC separator '\n') AS FromNames,count(CardNames2.FromName) AS NameCount,CardNames2.Benefactor AS Benefactor,CardNames2.Route AS Route from CardNames2 group by CardNames2.ForLast,CardNames2.ForFirst,CardNames2.Benefactor ;

-- --------------------------------------------------------

--
-- Structure for view _CostCalculator
--
DROP TABLE IF EXISTS `_CostCalculator`;

CREATE VIEW _CostCalculator  AS  select _OrderCostData.OrderNumber AS OrderNumber,_OrderCostData.Benefactor AS Benefactor,_OrderCostData.NumListed AS NumListed,_OrderCostData.NumLocal AS NumLocal,_OrderCostData.NumShip AS NumShip,_OrderCostData.NumExtra AS NumExtra,_OrderCostData.OldTotal AS OldTotal,_OrderCostData.Benefactor * Prices.Benefactor + _OrderCostData.NumListed * Prices.Basket + _OrderCostData.NumLocal * Prices.`Local` + _OrderCostData.NumShip * Prices.Shipped + _OrderCostData.NumExtra * Prices.Extra AS NewTotal from (_OrderCostData join Prices) order by _OrderCostData.OrderNumber ;

-- --------------------------------------------------------

--
-- Structure for view _DeliveryData
--
DROP TABLE IF EXISTS `_DeliveryData`;

CREATE VIEW _DeliveryData  AS  select p.NameID AS NameID,if(p.`Status` = _utf8'Non-Member',if(p.Staff = 1,_utf8'Staff',_utf8'Non-Member'),p.`Status`) AS `Status`,if(p.Delivery,_utf8'Local',_utf8'Ship') AS Delivery from ((People p left join OrderDetails od on(p.NameID = od.NameID)) left join Orders on(Orders.OrderNumber = od.OrderNumber)) where p.`Status` <> _utf8'Non-Member' or p.Staff = 1 or od.OrderNumber is not null and Orders.`Year` = year(curdate()) group by p.NameID ;

-- --------------------------------------------------------

--
-- Structure for view _ExtractNumFromMapsco
--
DROP TABLE IF EXISTS `_ExtractNumFromMapsco`;

CREATE VIEW _ExtractNumFromMapsco  AS  select People.NameID AS NameID,People.Mapsco AS Mapsco,if(cast(People.Mapsco as unsigned) > 99,cast(People.Mapsco as unsigned),if(cast(People.Mapsco as unsigned) > 9,concat(_utf8'0',cast(People.Mapsco as unsigned)),concat(_utf8'00',cast(People.Mapsco as unsigned)))) AS `Value` from People where People.Mapsco is not null and cast(left(People.Mapsco,3) as unsigned) > 0 ;

-- --------------------------------------------------------

--
-- Structure for view _OrderCostData
--
DROP TABLE IF EXISTS `_OrderCostData`;

CREATE VIEW _OrderCostData  AS  select Orders.OrderNumber AS OrderNumber,Orders.AllMembers AS Benefactor,sum(People.`Status` <> _utf8'Non-Member' or People.Staff = 1) AS NumListed,sum(People.`Status` = _utf8'Non-Member' and People.Staff = 0 and People.Delivery = 1) AS NumLocal,sum(People.`Status` = _utf8'Non-Member' and People.Staff = 0 and People.Delivery = 0) AS NumShip,Orders.ExtraBaskets AS NumExtra,Orders.Subtotal AS OldTotal from ((Orders left join OrderDetails on(Orders.OrderNumber = OrderDetails.OrderNumber)) join People on(OrderDetails.NameID = People.NameID)) where Orders.`Year` = year(curdate()) group by Orders.OrderNumber order by Orders.OrderNumber ;

-- --------------------------------------------------------

--
-- Structure for view _OrderFromTo
--
DROP TABLE IF EXISTS `_OrderFromTo`;

CREATE VIEW _OrderFromTo  AS  select Orders.NameID AS OrderedBy,OrderDetails.NameID AS OrderedFor from (Orders join OrderDetails on(Orders.OrderNumber = OrderDetails.OrderNumber)) where Orders.`Year` = year(curdate()) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table Admins
--
ALTER TABLE Admins
  ADD PRIMARY KEY (Email),
  ADD UNIQUE KEY Name (Name);

--
-- Indexes for table EmailSent
--
ALTER TABLE EmailSent
  ADD PRIMARY KEY (NameID,Year);

--
-- Indexes for table `Import`
--
ALTER TABLE `Import`
  ADD PRIMARY KEY (LastName,FirstNames);

--
-- Indexes for table MapscoData
--
ALTER TABLE MapscoData
  ADD PRIMARY KEY (City,StreetAddress);

--
-- Indexes for table Mapscos
--
ALTER TABLE Mapscos
  ADD PRIMARY KEY (NameID);

--
-- Indexes for table OrderDetails
--
ALTER TABLE OrderDetails
  ADD PRIMARY KEY (OrderNumber,NameID);

--
-- Indexes for table Orders
--
ALTER TABLE Orders
  ADD PRIMARY KEY (OrderNumber),
  ADD KEY Year (Year,NameID);

--
-- Indexes for table OrderWriteins
--
ALTER TABLE OrderWriteins
  ADD PRIMARY KEY (OrderNumber,Name);

--
-- Indexes for table People
--
ALTER TABLE People
  ADD PRIMARY KEY (NameID),
  ADD UNIQUE KEY FullName (LastName,FirstNames),
  ADD KEY Status (Status);

--
-- Indexes for table Prices
--
ALTER TABLE Prices
  ADD PRIMARY KEY (ID);

--
-- AUTO_INCREMENT for table People
--
ALTER TABLE People
  MODIFY NameID int(11) NOT NULL AUTO_INCREMENT;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
