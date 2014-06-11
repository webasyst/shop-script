<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="2.0"
                xmlns:html="http://www.w3.org/TR/html4/strict.dtd"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:output method="html" version="1.0" encoding="UTF-8" indent="yes"/>
    <xsl:template name="category">
        <xsl:variable name="itemTitle">
            <xsl:value-of select="Ид"/>
        </xsl:variable>
        <li title="{$itemTitle}">
            <a name="{$itemTitle}"></a>
            <i class="icon16 folder"></i>
            <span class="name">
                <xsl:value-of select="Наименование"/>
            </span>
            <xsl:for-each select="Группы">
                <xsl:call-template name="categories"></xsl:call-template>
            </xsl:for-each>
        </li>
    </xsl:template>

    <xsl:template name="categories">
        <ul class="menu-v with-icons">
            <xsl:for-each select="Группа">
                <xsl:call-template name="category">

                </xsl:call-template>
            </xsl:for-each>

        </ul>
    </xsl:template>
    <xsl:variable name="mail_type" select="'Почта'"/>
    <xsl:variable name="phone_type" select="'ТелефонРабочий'"/>
    <xsl:template name="buyer">
        <xsl:value-of select="ПолноеНаименование"/>
        <xsl:if test="Контакты/Контакт">
            <ul class="menu-v with-icons">
        <xsl:for-each select="Контакты/Контакт">
            <li>
                <xsl:if test="boolean(Тип/text()=$mail_type)"><i class="icon16 email"></i></xsl:if>
                <xsl:if test="boolean(Тип/text()=$phone_type)"><i class="icon16 phone"></i></xsl:if>
                <xsl:value-of select="Значение"/></li>

        </xsl:for-each>
            </ul>
        </xsl:if>


    </xsl:template>

    <xsl:template match="/">
        <html xmlns="http://www.w3.org/1999/xhtml">
            <head>
                <title>Сведения о магазине в формате CommerceML 2</title>
                <meta http-equiv="content-type" content="text/html; charset=utf-8"/>
                <link href="../../wa-content/css/wa/wa-1.0.css?v" rel="stylesheet" type="text/css"/>
                <link href="../../wa-apps/shop/css/shop.css?v" rel="stylesheet" type="text/css"/>
            </head>
            <body>
                <div id="wa-app">
                    <div class="sidebar left200px" style="position:fixed">
                        <div class="block">
                        <ul class="menu-v with-icons">
                            <li>
                                <a href="#"><i class="icon16 info"></i>Сведения о файле</a>
                            </li>
                            <xsl:if test="КоммерческаяИнформация/Документ">
                            <li>
                                <span class="count"><xsl:value-of select="count(КоммерческаяИнформация/Документ)"/> </span>
                                <a href="#Заказы"><i class="icon16 ss orders-all"></i>Заказы
                                </a>
                            </li>
                            </xsl:if>
                            <xsl:if test="КоммерческаяИнформация/Классификатор/Группы">
                            <li>
                                <span class="count"><xsl:value-of select="count(КоммерческаяИнформация/Классификатор/Группы//Группа)"/> </span>
                                <a href="#Группы"><i class="icon16 folder"></i>Категории
                                </a>
                            </li>
                            </xsl:if>
                            <xsl:if test="КоммерческаяИнформация/Каталог/Товары/Товар">
                            <li>
                                <span class="count"><xsl:value-of select="count(КоммерческаяИнформация/Каталог/Товары/Товар)"/> </span>
                                <a href="#Товары"><i class="icon16 folders"></i>Товары
                                </a>
                            </li>
                            </xsl:if>
                            <xsl:if test="КоммерческаяИнформация/Предложения/Предложение">
                            <li>
                                <span class="count"><xsl:value-of select="count(КоммерческаяИнформация/Предложения/Предложение)"/> </span>
                                <a href="#Предложения"><i class="icon16 folders"></i>Предложения
                                </a>
                            </li>
                            </xsl:if>
                        </ul>
                        </div>
                    </div>
                    <div class="block content left200px">
                        <h2>
                            <a name="#">Сведения о файле</a>
                        </h2>
                        <ul class="menu-v with-icons">
                        <li><i class="icon16 info"></i>
                        CommerceML <b><xsl:value-of select="КоммерческаяИнформация[@ВерсияСхемы]/@ВерсияСхемы"/></b>
                        </li>
                        <li><i class="icon16 clock"></i>
                            Дата формирования: <b><xsl:value-of select="КоммерческаяИнформация[@ДатаФормирования]/@ДатаФормирования"/></b>
                        </li>
                        <xsl:if test="КоммерческаяИнформация/Классификатор/Владелец/ПолноеНаименование">
                        <li><i class="icon16 user"></i>
                            Владелец
                                <b>
                                    <xsl:value-of select="КоммерческаяИнформация/Классификатор/Владелец/ПолноеНаименование"/>
                                </b>
                        </li>
                        </xsl:if>
                            <xsl:if test="КоммерческаяИнформация/comment()[1]">
                                <li><i class="icon16 info"></i>
                                    Платформа: <xsl:value-of select="КоммерческаяИнформация/comment()[1]" />
                                </li>
                            </xsl:if>
                            <xsl:if test="(//comment())[last()]">
                                <li><i class="icon16 info"></i>
                                    <xsl:value-of select="(//comment())[last()]" />
                                </li>
                            </xsl:if>
                        </ul>
                    </div>
                    <xsl:if test="КоммерческаяИнформация/Документ">
                    <div class="block content left200px">

                        <h2>
                            <a name="Заказы">Сведения о заказах</a>
                        </h2>
                        <table class="zebra">
                            <thead>
                            <tr style="border-bottom:1px black solid;">
                                <th>Номер</th>
                                <th>Дата</th>
                                <th>Сумма</th>
                                <th>Валюта</th>
                                <th>Курс</th>
                                <th>Покупатель</th>
                                <th>Способ оплаты</th>
                                <th>Доставка</th>
                                <th>Адрес доставки</th>
                                <th>Состав заказа</th>
                            </tr>
                            </thead>
                            <tbody>
                            <xsl:for-each select="КоммерческаяИнформация/Документ">
                                <xsl:variable name="currency">
                                    <xsl:value-of select="Валюта"/>
                                </xsl:variable>
                                <tr>
                                    <td>
                                        <xsl:value-of select="Номер"/>
                                    </td>
                                    <td>
                                        <xsl:value-of select="Дата"/>
                                    </td>
                                    <td>
                                        <xsl:value-of select="Сумма"/>
                                    </td>
                                    <td>
                                        <xsl:value-of select="Валюта"/>
                                    </td>
                                    <td>
                                        <xsl:value-of select="Курс"/>
                                    </td>
                                    <td>
                                        <xsl:for-each select="Контрагенты/Контрагент[Роль='Покупатель']">
                                            <xsl:call-template name="buyer"></xsl:call-template>
                                        </xsl:for-each>
                                    </td>

                                    <td>
                                        <xsl:for-each select="ЗначенияРеквизитов/ЗначениеРеквизита">
                                            <xsl:if test="Наименование = 'Способ оплаты'">
                                                <xsl:value-of select="Значение"/>
                                            </xsl:if>
                                        </xsl:for-each>
                                    </td>


                                    <td>
                                        <xsl:for-each select="ЗначенияРеквизитов/ЗначениеРеквизита">
                                            <xsl:if test="Наименование = 'Способ доставки'">
                                                <xsl:value-of select="Значение"/>
                                            </xsl:if>
                                        </xsl:for-each>
                                        <xsl:for-each select="Товары/Товар[Ид='ORDER_DELIVERY']">
                                            <br/>
                                            <xsl:value-of select="ЦенаЗаЕдиницу"/>
                                            <xsl:value-of select="$currency"/>
                                        </xsl:for-each>
                                    </td>

                                    <td>
                                        <xsl:text> </xsl:text>
                                        <xsl:if test="Контрагенты/Контрагент[Роль='Покупатель']/АдресРегистрации[Вид='Адрес доставки']/АдресноеПоле">
                                        <ul class="menu-v with-icons">
                                        <xsl:for-each
                                                select="Контрагенты/Контрагент[Роль='Покупатель']/АдресРегистрации[Вид='Адрес доставки']/АдресноеПоле">
                                            <xsl:variable name="itemTitle">
                                                <xsl:value-of select="Тип"/>
                                            </xsl:variable>
                                            <li>
                                            <span title="{$itemTitle}">
                                                <xsl:value-of select='Значение'/>
                                            </span>
                                            </li>

                                        </xsl:for-each>
                                        </ul>
                                        </xsl:if>
                                    </td>


                                    <td>
                                        <ul class="menu-v with-icons">
                                            <xsl:for-each select="Товары/Товар">
                                                <xsl:if test="Ид != 'ORDER_DELIVERY'">
                                                    <li><i class="icon16 box"></i>
                                                        <xsl:value-of select="Наименование"/><xsl:text> </xsl:text><xsl:value-of
                                                            select="Количество"/><xsl:value-of select="БазоваяЕдиница"/>
                                                        <xsl:text> по </xsl:text><xsl:value-of select="ЦенаЗаЕдиницу"/>
                                                        <xsl:value-of select="$currency"/>
                                                    </li>
                                                </xsl:if>
                                            </xsl:for-each>
                                        </ul>

                                    </td>
                                </tr>
                            </xsl:for-each>
                            </tbody>
                        </table>
                    </div>
                    </xsl:if>
                    <xsl:if test="КоммерческаяИнформация/Каталог/Товары/Товар">
                    <div class="block content left200px">
                        <h2>
                            <a name="Группы">Сведения о категориях</a>
                        </h2>
                        <xsl:for-each select="КоммерческаяИнформация/Классификатор/Группы">
                            <xsl:call-template name="categories"></xsl:call-template>
                        </xsl:for-each>

                    </div>
                    <div class="block content left200px">
                        <h2>
                            <a name="Товары">Сведения о товарах</a>
                        </h2>
                        <table class="zebra">
                            <thead>
                                <tr>
                                    <th>Наименование</th>
                                    <th>Артикул</th>
                                    <th>CML</th>
                                </tr>
                            </thead>
                            <tbody>
                                <xsl:for-each select="КоммерческаяИнформация/Каталог/Товары/Товар">
                                    <xsl:variable name="itemTitle">
                                        <xsl:value-of select="Ид"/>
                                    </xsl:variable>
                                    <tr title="{$itemTitle}">
                                        <td>
                                            <xsl:value-of select="Наименование"/>
                                        </td>

                                        <td>
                                            <xsl:value-of select="Артикул"/>
                                        </td>
                                        <td>
                                            <span class="hint"><xsl:value-of select="$itemTitle"/></span>
                                        </td>
                                    </tr>
                                </xsl:for-each>
                            </tbody>
                        </table>
                    </div>
                    </xsl:if>
                    <xsl:if test="КоммерческаяИнформация/Предложения/Предложение">
                    <div class="block content left200px">
                        <h2>
                            <a name="Предложения">Сведения о товарных предложения</a>
                        </h2>

                        <table class="zebra">
                            <thead>
                                <tr>

                                    <th>Наименование</th>
                                    <th>Цена</th>
                                    <th>Валюта</th>
                                    <th>Остаток</th>
                                    <th>CML</th>
                                </tr>
                            </thead>
                            <tbody>
                                <xsl:for-each select="КоммерческаяИнформация/Предложения/Предложение">
                                    <xsl:variable name="itemTitle">
                                        <xsl:value-of select="Ид"/>
                                    </xsl:variable>
                                    <tr title="{$itemTitle}">
                                        <td>
                                            <xsl:value-of select="Наименование"/>
                                        </td>
                                        <td>
                                            <xsl:value-of select="Цены/Цена/ЦенаЗаЕдиницу"/>
                                        </td>
                                        <td>
                                            <xsl:value-of select="Цены/Цена/Валюта"/>
                                        </td>
                                        <td>
                                            <xsl:value-of select="Количество"/>
                                        </td>
                                        <td>
                                            <span class="hint"><xsl:value-of select="$itemTitle"/></span>
                                        </td>
                                    </tr>
                                </xsl:for-each>
                            </tbody>
                        </table>
                    </div>
                    </xsl:if>
                </div>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>